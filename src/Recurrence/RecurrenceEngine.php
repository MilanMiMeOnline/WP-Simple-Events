<?php
/**
 * Recurrence expansion boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use MiMe\WPSimpleEvents\Domain\EventDateRange;

/**
 * Keeps persistence and editors independent from expansion implementation.
 */
interface RecurrenceEngine {
	/**
	 * Expand one validated definition inside one explicit bounded window.
	 *
	 * @param EventDateRange             $template   Validated template occurrence range.
	 * @param RecurrenceDefinition       $definition Validated recurrence definition.
	 * @param RecurrenceGenerationWindow $window     Explicit bounded output window.
	 * @throws RecurrenceGenerationException When safe deterministic expansion cannot complete.
	 */
	public function generate(
		EventDateRange $template,
		RecurrenceDefinition $definition,
		RecurrenceGenerationWindow $window
	): RecurrenceGenerationResult;
}
