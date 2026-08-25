<?php
/**
 * Supported recurrence frequencies.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Version-one rule frequencies; hourly and minutely rules are excluded.
 */
enum RecurrenceFrequency: string {
	case DAILY   = 'daily';
	case WEEKLY  = 'weekly';
	case MONTHLY = 'monthly';
	case YEARLY  = 'yearly';
}
