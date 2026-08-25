<?php
/**
 * WordPress recurrence aggregate persistence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use RuntimeException;

/**
 * Reads and replaces the protected canonical aggregate metadata string.
 */
final readonly class WordPressRecurrenceAggregateStore implements ConcurrentRecurrenceAggregateStore {
	/**
	 * Create the WordPress aggregate store.
	 *
	 * @param RecurrenceAggregateJsonCodec $codec    Bounded canonical JSON codec.
	 * @param RecurrenceAggregateRevision  $revisions Optimistic concurrency tokens.
	 */
	public function __construct(
		private RecurrenceAggregateJsonCodec $codec = new RecurrenceAggregateJsonCodec(),
		private RecurrenceAggregateRevision $revisions = new RecurrenceAggregateRevision()
	) {}

	/**
	 * Load a complete aggregate, or null when no definition exists.
	 *
	 * Corrupt non-empty metadata throws so callers cannot mistake corruption for a
	 * deliberately one-off event.
	 *
	 * @param int $event_id Canonical event post ID.
	 * @throws InvalidArgumentException When stored metadata is corrupt.
	 */
	public function load( int $event_id ): ?RecurrenceAggregate {
		if ( $event_id <= 0 ) {
			return null;
		}

		$value = get_post_meta( $event_id, EventMeta::RECURRENCE, true );

		if ( '' === $value ) {
			return null;
		}

		return $this->codec->decode( $value );
	}

	/**
	 * Atomically replace one complete aggregate metadata value.
	 *
	 * @param int                 $event_id Canonical event post ID.
	 * @param RecurrenceAggregate $aggregate Validated complete aggregate.
	 * @throws RuntimeException When the validated aggregate exceeds the storage bound.
	 */
	public function replace( int $event_id, RecurrenceAggregate $aggregate ): bool {
		if ( $event_id <= 0 ) {
			return false;
		}

		$encoded = $this->codec->encode( $aggregate );
		$current = get_post_meta( $event_id, EventMeta::RECURRENCE, true );

		if ( $current === $encoded ) {
			return true;
		}

		$result = update_post_meta( $event_id, EventMeta::RECURRENCE, $encoded );

		return false !== $result
			&& get_post_meta( $event_id, EventMeta::RECURRENCE, true ) === $encoded;
	}

	/**
	 * Load canonical state with its deterministic editor revision.
	 *
	 * @param int $event_id Canonical event post ID.
	 * @throws InvalidArgumentException When stored metadata is corrupt.
	 */
	public function snapshot( int $event_id ): RecurrenceAggregateSnapshot {
		$aggregate = $this->load( $event_id );

		return new RecurrenceAggregateSnapshot( $aggregate, $this->revisions->token( $aggregate ) );
	}

	/**
	 * Replace canonical JSON only when the editor revision is still current.
	 *
	 * @param int                 $event_id         Canonical event post ID.
	 * @param RecurrenceAggregate $aggregate        Proposed complete aggregate.
	 * @param string              $expected_revision Revision used for preview.
	 * @throws InvalidArgumentException When stored metadata is corrupt.
	 * @throws RuntimeException When canonical JSON exceeds its storage bound.
	 */
	public function replace_if_current(
		int $event_id,
		RecurrenceAggregate $aggregate,
		string $expected_revision
	): RecurrenceAggregateWriteStatus {
		if ( $event_id <= 0 || ! $this->revisions->valid( $expected_revision ) ) {
			return RecurrenceAggregateWriteStatus::FAILED;
		}

		$current_aggregate = $this->load( $event_id );
		$current_revision  = $this->revisions->token( $current_aggregate );

		if ( ! hash_equals( $current_revision, $expected_revision ) ) {
			return RecurrenceAggregateWriteStatus::CONFLICT;
		}

		$encoded = $this->codec->encode( $aggregate );
		$current = get_post_meta( $event_id, EventMeta::RECURRENCE, true );

		if ( ! is_string( $current ) ) {
			return RecurrenceAggregateWriteStatus::FAILED;
		}

		if ( $current === $encoded ) {
			return RecurrenceAggregateWriteStatus::UNCHANGED;
		}

		$stored = '' === $current
			? add_post_meta( $event_id, EventMeta::RECURRENCE, $encoded, true )
			: update_post_meta( $event_id, EventMeta::RECURRENCE, $encoded, $current );

		if ( false !== $stored ) {
			return RecurrenceAggregateWriteStatus::STORED;
		}

		$latest = get_post_meta( $event_id, EventMeta::RECURRENCE, true );

		if ( $latest === $encoded ) {
			return RecurrenceAggregateWriteStatus::STORED;
		}

		$latest_aggregate = $this->load( $event_id );

		return hash_equals( $expected_revision, $this->revisions->token( $latest_aggregate ) )
			? RecurrenceAggregateWriteStatus::FAILED
			: RecurrenceAggregateWriteStatus::CONFLICT;
	}

	/**
	 * Remove canonical JSON only when the editor revision is still current.
	 *
	 * The raw current value is passed to WordPress as the exact delete compare
	 * value so an intervening editor cannot have its aggregate removed.
	 *
	 * @param int    $event_id         Canonical event post ID.
	 * @param string $expected_revision Revision used for preview.
	 * @throws InvalidArgumentException When stored metadata is corrupt.
	 */
	public function remove_if_current(
		int $event_id,
		string $expected_revision
	): RecurrenceAggregateWriteStatus {
		if ( $event_id <= 0 || ! $this->revisions->valid( $expected_revision ) ) {
			return RecurrenceAggregateWriteStatus::FAILED;
		}

		$current_aggregate = $this->load( $event_id );

		if ( ! hash_equals( $this->revisions->token( $current_aggregate ), $expected_revision ) ) {
			return RecurrenceAggregateWriteStatus::CONFLICT;
		}

		if ( null === $current_aggregate ) {
			return RecurrenceAggregateWriteStatus::UNCHANGED;
		}

		$current = get_post_meta( $event_id, EventMeta::RECURRENCE, true );

		if ( ! is_string( $current ) || '' === $current ) {
			return RecurrenceAggregateWriteStatus::FAILED;
		}

		if ( delete_post_meta( $event_id, EventMeta::RECURRENCE, $current )
			&& '' === get_post_meta( $event_id, EventMeta::RECURRENCE, true )
		) {
			return RecurrenceAggregateWriteStatus::STORED;
		}

		$latest_aggregate = $this->load( $event_id );

		return hash_equals( $expected_revision, $this->revisions->token( $latest_aggregate ) )
			? RecurrenceAggregateWriteStatus::FAILED
			: RecurrenceAggregateWriteStatus::CONFLICT;
	}
}
