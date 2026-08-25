<?php
/**
 * Optimistic recurrence aggregate storage boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Adds atomic compare-and-replace semantics for interactive editors.
 */
interface ConcurrentRecurrenceAggregateStore extends RecurrenceAggregateStore {
	/**
	 * Load canonical state with its deterministic revision token.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function snapshot( int $event_id ): RecurrenceAggregateSnapshot;

	/**
	 * Replace state only when the expected revision is still current.
	 *
	 * @param int                 $event_id         Canonical event post ID.
	 * @param RecurrenceAggregate $aggregate        Proposed complete aggregate.
	 * @param string              $expected_revision Revision used by the editor preview.
	 */
	public function replace_if_current(
		int $event_id,
		RecurrenceAggregate $aggregate,
		string $expected_revision
	): RecurrenceAggregateWriteStatus;

	/**
	 * Remove state only when the expected revision is still current.
	 *
	 * @param int    $event_id         Canonical event post ID.
	 * @param string $expected_revision Revision used by the editor preview.
	 */
	public function remove_if_current(
		int $event_id,
		string $expected_revision
	): RecurrenceAggregateWriteStatus;
}
