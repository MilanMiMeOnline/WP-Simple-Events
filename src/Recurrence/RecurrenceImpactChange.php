<?php
/**
 * Visible recurrence impact change types.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Describes one or more effects on one immutable occurrence identity.
 */
enum RecurrenceImpactChange: string {
	case ADDED          = 'added';
	case REMOVED        = 'removed';
	case MOVED          = 'moved';
	case STATUS_CHANGED = 'status_changed';
	case SOURCE_CHANGED = 'source_changed';
}
