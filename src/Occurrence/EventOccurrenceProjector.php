<?php
/**
 * Event-to-occurrence projection boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Keeps canonical event writes independent from projection storage mechanics.
 */
interface EventOccurrenceProjector {
	/**
	 * Replace the one-off projection, or remove it for an incomplete draft.
	 *
	 * @param int                 $event_id   Canonical event post ID.
	 * @param EventDateRange|null $date_range Validated range or incomplete draft.
	 * @param EventStatus         $status     Effective event status.
	 */
	public function project_one_off( int $event_id, ?EventDateRange $date_range, EventStatus $status ): bool;
}
