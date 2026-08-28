<?php
/**
 * Supported add-to-calendar providers.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

/** Keeps provider selection on one stable allowlist. */
enum CalendarProvider: string {
	case ICS     = 'ics';
	case GOOGLE  = 'google';
	case OUTLOOK = 'outlook';
}
