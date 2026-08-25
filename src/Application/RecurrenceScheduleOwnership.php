<?php
/**
 * Recurrence schedule ownership boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Content\EventMeta;

/**
 * Keeps every non-empty protected recurrence value on the recurrence path.
 */
final readonly class RecurrenceScheduleOwnership {
	/**
	 * Determine whether the protected aggregate owns one event's schedule.
	 *
	 * Malformed structured metadata remains owned. It must be repaired through
	 * recurrence tooling rather than becoming an accidental one-off event.
	 *
	 * @param int $event_id Event post ID.
	 */
	public function owns( int $event_id ): bool {
		$value = get_post_meta( $event_id, EventMeta::RECURRENCE, true );

		if ( is_string( $value ) ) {
			return '' !== trim( $value );
		}

		return null !== $value && false !== $value && 0 !== $value && array() !== $value;
	}
}
