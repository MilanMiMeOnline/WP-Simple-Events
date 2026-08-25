<?php
/**
 * Recurrence persistence error codes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Stable non-sensitive errors for the future editor API boundary.
 */
enum RecurrencePersistenceError: string {
	case INVALID_EVENT      = 'invalid_event';
	case FORBIDDEN          = 'forbidden';
	case IDENTITY_MISMATCH  = 'identity_mismatch';
	case TIMEZONE_MISMATCH  = 'timezone_mismatch';
	case STALE_REVISION     = 'stale_revision';
	case INDEX_GUARD_FAILED = 'index_guard_failed';
	case STORAGE_FAILED     = 'storage_failed';
	case PROJECTION_FAILED  = 'projection_failed';
}
