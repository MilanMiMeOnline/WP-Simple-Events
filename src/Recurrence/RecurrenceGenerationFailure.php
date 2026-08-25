<?php
/**
 * Recurrence generation failure reasons.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Stable reasons that later editor adapters can translate into field errors.
 */
enum RecurrenceGenerationFailure: string {
	case INVALID_LOCAL_TIME           = 'invalid_local_time';
	case ROW_LIMIT_EXCEEDED           = 'row_limit_exceeded';
	case EVALUATION_LIMIT_REACHED     = 'evaluation_limit_reached';
	case DATE_OUTSIDE_SUPPORTED_RANGE = 'date_outside_supported_range';
}
