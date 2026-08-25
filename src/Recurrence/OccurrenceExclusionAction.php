<?php
/**
 * Recurrence exclusion actions.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Distinguishes a hidden slot from a directly readable cancellation.
 */
enum OccurrenceExclusionAction: string {
	case SKIP   = 'skip';
	case CANCEL = 'cancel';
}
