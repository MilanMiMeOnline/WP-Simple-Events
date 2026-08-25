<?php
/**
 * Conditional recurrence aggregate write outcomes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Distinguishes safe no-ops, stale editors and storage failures.
 */
enum RecurrenceAggregateWriteStatus: string {
	case STORED    = 'stored';
	case UNCHANGED = 'unchanged';
	case CONFLICT  = 'conflict';
	case FAILED    = 'failed';
}
