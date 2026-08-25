<?php
/**
 * Recurrence aggregate persistence boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Stores one complete canonical aggregate independently from editor authorization.
 */
interface RecurrenceAggregateStore {
	/**
	 * Load a complete aggregate, or null when the event remains one-off.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function load( int $event_id ): ?RecurrenceAggregate;

	/**
	 * Atomically replace one complete aggregate metadata value.
	 *
	 * @param int                 $event_id Canonical event post ID.
	 * @param RecurrenceAggregate $aggregate Validated complete aggregate.
	 */
	public function replace( int $event_id, RecurrenceAggregate $aggregate ): bool;
}
