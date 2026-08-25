<?php
/**
 * Authorized recurrence aggregate persistence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Recurrence\ConcurrentRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateWriteStatus;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use RuntimeException;

/**
 * Replaces a complete aggregate only for an authorized, identity-matched event.
 */
final readonly class RecurrenceAggregatePersistence {
	/**
	 * Create the authorized persistence service.
	 *
	 * @param RecurrenceAggregateStore    $store     Internal canonical metadata store.
	 * @param RecurrenceAggregateRevision $revisions Optimistic concurrency token validator.
	 */
	public function __construct(
		private RecurrenceAggregateStore $store = new WordPressRecurrenceAggregateStore(),
		private RecurrenceAggregateRevision $revisions = new RecurrenceAggregateRevision()
	) {}

	/**
	 * Replace one complete aggregate and invalidate its derived projection first.
	 *
	 * @param int                 $event_id         Canonical event post ID.
	 * @param RecurrenceAggregate $aggregate        Validated complete aggregate.
	 * @param string|null         $expected_revision Optional editor revision used for preview.
	 */
	public function replace(
		int $event_id,
		RecurrenceAggregate $aggregate,
		?string $expected_revision = null
	): RecurrencePersistenceResult {
		if ( $event_id <= 0 || EventPostType::POST_TYPE !== get_post_type( $event_id ) ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::INVALID_EVENT );
		}

		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::FORBIDDEN );
		}

		$series_uid = get_post_meta( $event_id, EventMeta::SERIES_UID, true );
		$timezone   = get_post_meta( $event_id, EventMeta::TIMEZONE, true );

		if ( ! is_string( $series_uid ) || $aggregate->series_uid !== $series_uid ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::IDENTITY_MISMATCH );
		}

		if ( ! is_string( $timezone ) || $aggregate->timezone !== $timezone ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::TIMEZONE_MISMATCH );
		}

		if ( null !== $expected_revision ) {
			if ( ! $this->revisions->valid( $expected_revision )
				|| ! $this->store instanceof ConcurrentRecurrenceAggregateStore
			) {
				return RecurrencePersistenceResult::failure( RecurrencePersistenceError::STALE_REVISION );
			}

			try {
				$snapshot = $this->store->snapshot( $event_id );
			} catch ( InvalidArgumentException | RuntimeException ) {
				return RecurrencePersistenceResult::failure( RecurrencePersistenceError::STORAGE_FAILED );
			}

			if ( ! hash_equals( $snapshot->revision, $expected_revision ) ) {
				return RecurrencePersistenceResult::failure( RecurrencePersistenceError::STALE_REVISION );
			}

			$existing = $snapshot->aggregate;
		} else {
			try {
				$existing = $this->store->load( $event_id );
			} catch ( InvalidArgumentException ) {
				// An authorized non-editor replacement may repair corrupt canonical metadata.
				$existing = null;
			}
		}

		if ( $aggregate == $existing ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Value objects need semantic equality across decoded instances.
			return RecurrencePersistenceResult::success( false );
		}

		if ( ! $this->mark_projection_dirty( $event_id ) ) {
			return RecurrencePersistenceResult::failure( RecurrencePersistenceError::INDEX_GUARD_FAILED );
		}

		if ( null !== $expected_revision ) {
			try {
				$status = $this->store->replace_if_current( $event_id, $aggregate, $expected_revision );
			} catch ( InvalidArgumentException | RuntimeException ) {
				$status = RecurrenceAggregateWriteStatus::FAILED;
			}

			return match ( $status ) {
				RecurrenceAggregateWriteStatus::STORED    => RecurrencePersistenceResult::success( true ),
				RecurrenceAggregateWriteStatus::UNCHANGED => RecurrencePersistenceResult::success( false ),
				RecurrenceAggregateWriteStatus::CONFLICT  => RecurrencePersistenceResult::failure( RecurrencePersistenceError::STALE_REVISION ),
				RecurrenceAggregateWriteStatus::FAILED    => RecurrencePersistenceResult::failure( RecurrencePersistenceError::STORAGE_FAILED ),
			};
		}

		try {
			$stored = $this->store->replace( $event_id, $aggregate );
		} catch ( RuntimeException ) {
			$stored = false;
		}

		return $stored
			? RecurrencePersistenceResult::success( true )
			: RecurrencePersistenceResult::failure( RecurrencePersistenceError::STORAGE_FAILED );
	}

	/**
	 * Fail closed before canonical recurrence can diverge from its read model.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	private function mark_projection_dirty( int $event_id ): bool {
		$current = get_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

		if ( $this->is_dirty_marker( $current ) ) {
			return true;
		}

		$result = update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );
		$stored = get_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

		return false !== $result && $this->is_dirty_marker( $stored );
	}

	/**
	 * Recognize the two canonical WordPress representations of a true boolean.
	 *
	 * @param mixed $value Stored metadata value.
	 */
	private function is_dirty_marker( mixed $value ): bool {
		return true === $value || '1' === $value;
	}
}
