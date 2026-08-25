<?php
/**
 * Recurrence editor scopes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Requires the editor to choose the breadth of a recurrence mutation first.
 */
enum RecurrenceEditScope: string {
	case ONLY_THIS          = 'only_this';
	case THIS_AND_FOLLOWING = 'this_and_following';
	case COMPLETE_SERIES    = 'complete_series';
}
