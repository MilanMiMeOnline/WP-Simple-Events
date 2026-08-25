<?php
/**
 * One-off occurrence index repair outcomes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Provides stable accounting for bounded migration batches.
 */
enum OccurrenceIndexRepairStatus: string {
	case INDEXED   = 'indexed';
	case UNCHANGED = 'unchanged';
	case INVALID   = 'invalid';
	case FAILED    = 'failed';
}
