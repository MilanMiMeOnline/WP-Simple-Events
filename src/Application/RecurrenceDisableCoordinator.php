<?php
/**
 * Recurrence-to-one-off conversion coordination.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrenceProjector;
use MiMe\WPSimpleEvents\Occurrence\OneOffOccurrenceProjector;
use MiMe\WPSimpleEvents\Recurrence\ConcurrentRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateWriteStatus;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use RuntimeException;

/**
 * Retains one selected effective occurrence as the canonical one-off event.
 */
final readonly class RecurrenceDisableCoordinator {
	/**
	 * Metadata replaced by one effective occurrence.
	 *
	 * @var list<string>
	 */
	private const DATE_META_KEYS = array(
		EventMeta::START_LOCAL,
		EventMeta::END_LOCAL,
		EventMeta::START_UTC,
		EventMeta::END_UTC,
		EventMeta::ALL_DAY,
		EventMeta::TIMEZONE,
		EventMeta::STATUS,
	);

	/**
	 * Create the conversion coordinator.
	 *
	 * @param ConcurrentRecurrenceAggregateStore $store     Atomic recurrence storage.
	 * @param EventOccurrenceProjector           $projector Derived one-off projector.
	 */
	public function __construct(
		private ConcurrentRecurrenceAggregateStore $store = new WordPressRecurrenceAggregateStore(),
		private EventOccurrenceProjector $projector = new OneOffOccurrenceProjector()
	) {}

	/**
	 * Remove recurrence and keep one selected effective occurrence.
	 *
	 * @param int             $event_id         Canonical event post ID.
	 * @param EventOccurrence $survivor         Exact effective occurrence retained as one-off.
	 * @param string          $expected_revision Revision bound into the confirmed preview.
	 */
	public function disable(
		int $event_id,
		EventOccurrence $survivor,
		string $expected_revision
	): RecurrencePersistenceResult {
		if ( $event_id <= 0
			|| $survivor->event_id !== $event_id
			|| EventPostType::POST_TYPE !== get_post_type( $event_id )
		) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::INVALID_EVENT );
		}

		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::FORBIDDEN );
		}

		$series_uid = get_post_meta( $event_id, EventMeta::SERIES_UID, true );

		if ( ! is_string( $series_uid ) || $survivor->identity->series_uid() !== $series_uid ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::IDENTITY_MISMATCH );
		}

		try {
			$snapshot = $this->store->snapshot( $event_id );
		} catch ( InvalidArgumentException | RuntimeException ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::STORAGE_FAILED );
		}

		if ( null === $snapshot->aggregate
			|| ! hash_equals( $snapshot->revision, $expected_revision )
		) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::STALE_REVISION );
		}

		if ( ! $this->mark_projection_dirty( $event_id ) ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::INDEX_GUARD_FAILED );
		}

		$desired  = $this->desired_metadata( $survivor );
		$previous = $this->capture_metadata( $event_id );

		if ( ! $this->prepare_metadata( $event_id, $desired ) ) {
			$this->rollback_metadata( $event_id, $desired, $previous );

			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::STORAGE_FAILED );
		}

		try {
			$status = $this->store->remove_if_current( $event_id, $expected_revision );
		} catch ( InvalidArgumentException | RuntimeException ) {
			$status = RecurrenceAggregateWriteStatus::FAILED;
		}

		if ( RecurrenceAggregateWriteStatus::STORED !== $status ) {
			$this->rollback_metadata( $event_id, $desired, $previous );

			return RecurrencePersistenceResult::failure(
				RecurrenceAggregateWriteStatus::CONFLICT === $status
					? RecurrencePersistenceError::STALE_REVISION
					: RecurrencePersistenceError::STORAGE_FAILED
			);
		}

		if ( ! $this->projector->project_one_off(
			$event_id,
			$survivor->date_range,
			$survivor->status
		) ) {
			update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

			return RecurrencePersistenceResult::failure(
				RecurrencePersistenceError::PROJECTION_FAILED,
				true
			);
		}

		return RecurrencePersistenceResult::success( true );
	}

	/**
	 * Convert an occurrence into the complete canonical one-off date record.
	 *
	 * @param EventOccurrence $survivor Effective occurrence to retain.
	 * @return array<string, bool|int|string>
	 */
	private function desired_metadata( EventOccurrence $survivor ): array {
		$range = $survivor->date_range;

		return array(
			EventMeta::START_LOCAL => $range->start_local(),
			EventMeta::END_LOCAL   => $range->end_local(),
			EventMeta::START_UTC   => $range->start_utc(),
			EventMeta::END_UTC     => $range->end_utc(),
			EventMeta::ALL_DAY     => $range->all_day(),
			EventMeta::TIMEZONE    => $range->timezone(),
			EventMeta::STATUS      => $survivor->status->value,
		);
	}

	/**
	 * Capture exact metadata state for a compare-aware rollback.
	 *
	 * @param int $event_id Canonical event post ID.
	 * @return array<string, array{exists: bool, value: mixed}>
	 */
	private function capture_metadata( int $event_id ): array {
		$captured = array();

		foreach ( self::DATE_META_KEYS as $meta_key ) {
			$captured[ $meta_key ] = array(
				'exists' => metadata_exists( 'post', $event_id, $meta_key ),
				'value'  => get_post_meta( $event_id, $meta_key, true ),
			);
		}

		return $captured;
	}

	/**
	 * Prepare each canonical field with an exact previous-value comparison.
	 *
	 * @param int                            $event_id Canonical event post ID.
	 * @param array<string, bool|int|string> $desired  Desired one-off values.
	 */
	private function prepare_metadata( int $event_id, array $desired ): bool {
		foreach ( $desired as $meta_key => $value ) {
			$current = get_post_meta( $event_id, $meta_key, true );

			if ( $this->equivalent( $meta_key, $current, $value ) ) {
				continue;
			}

			$result = update_post_meta( $event_id, $meta_key, $value, $current );

			if ( false === $result
				|| ! $this->equivalent( $meta_key, get_post_meta( $event_id, $meta_key, true ), $value )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Restore only fields that still contain this operation's prepared value.
	 *
	 * @param int                                              $event_id Canonical event post ID.
	 * @param array<string, bool|int|string>                   $desired  Prepared values.
	 * @param array<string, array{exists: bool, value: mixed}> $previous Exact previous state.
	 */
	private function rollback_metadata( int $event_id, array $desired, array $previous ): void {
		foreach ( array_reverse( self::DATE_META_KEYS ) as $meta_key ) {
			$current = get_post_meta( $event_id, $meta_key, true );

			if ( ! $this->equivalent( $meta_key, $current, $desired[ $meta_key ] ) ) {
				continue;
			}

			if ( $previous[ $meta_key ]['exists'] ) {
				update_post_meta( $event_id, $meta_key, $previous[ $meta_key ]['value'], $current );
				continue;
			}

			delete_post_meta( $event_id, $meta_key, $current );
		}
	}

	/**
	 * Compare WordPress scalar representations without weakening date strings.
	 *
	 * @param string $meta_key Metadata key.
	 * @param mixed  $actual   Stored WordPress value.
	 * @param mixed  $expected Validated domain value.
	 */
	private function equivalent( string $meta_key, mixed $actual, mixed $expected ): bool {
		if ( EventMeta::START_UTC === $meta_key || EventMeta::END_UTC === $meta_key ) {
			return is_numeric( $actual ) && (int) $actual === (int) $expected;
		}

		if ( EventMeta::ALL_DAY === $meta_key ) {
			return ( true === $actual || 1 === $actual || '1' === $actual ) === (bool) $expected;
		}

		return is_string( $actual ) && $actual === $expected;
	}

	/**
	 * Fail closed before canonical recurrence can diverge from its read model.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	private function mark_projection_dirty( int $event_id ): bool {
		$current = get_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

		if ( true === $current || '1' === $current ) {
			return true;
		}

		$result = update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );
		$stored = get_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

		return false !== $result && ( true === $stored || '1' === $stored );
	}
}
