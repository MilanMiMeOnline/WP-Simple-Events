<?php
/**
 * Recurring event projection boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Converts one complete canonical series into one complete occurrence generation.
 */
interface RecurringEventOccurrenceProjector {
	/**
	 * Build and activate one complete bounded recurring projection.
	 *
	 * @param int                        $event_id     Canonical event post ID.
	 * @param RecurrenceAggregate        $aggregate    Validated complete aggregate.
	 * @param EventStatus                $series_status Inherited event status.
	 * @param RecurrenceGenerationWindow $window       Explicit bounded projection window.
	 */
	public function project(
		int $event_id,
		RecurrenceAggregate $aggregate,
		EventStatus $series_status,
		RecurrenceGenerationWindow $window
	): bool;
}
