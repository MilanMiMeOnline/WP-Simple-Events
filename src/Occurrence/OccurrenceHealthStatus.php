<?php
/**
 * Occurrence index health states.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Small administrator-facing state machine.
 */
enum OccurrenceHealthStatus: string {
	case HEALTHY       = 'healthy';
	case BUILDING      = 'building';
	case REPAIR_NEEDED = 'repair_needed';
	case UNAVAILABLE   = 'unavailable';
}
