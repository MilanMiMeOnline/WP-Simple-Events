<?php
/**
 * Monthly recurrence modes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Monthly rules use either a calendar day or one ordinal weekday.
 */
enum MonthlyRecurrenceMode: string {
	case DAY_OF_MONTH    = 'day_of_month';
	case ORDINAL_WEEKDAY = 'ordinal_weekday';
}
