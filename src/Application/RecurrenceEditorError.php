<?php
/**
 * Recurrence editor application error codes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Stable non-sensitive failures for the authenticated editor boundary.
 */
enum RecurrenceEditorError: string {
	case INVALID_EVENT        = 'invalid_event';
	case FORBIDDEN            = 'forbidden';
	case INVALID_STATE        = 'invalid_state';
	case STALE_REVISION       = 'stale_revision';
	case INVALID_PROPOSAL     = 'invalid_proposal';
	case INVALID_CONFIRMATION = 'invalid_confirmation';
}
