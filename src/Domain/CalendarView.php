<?php
/**
 * Public calendar views.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/**
 * Calendar views intentionally supported in version 1.
 */
enum CalendarView: string {
	case MONTH = 'month';
	case LIST  = 'list';
}
