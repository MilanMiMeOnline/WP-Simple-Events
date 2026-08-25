<?php
/**
 * One recurrence schedule segment.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;

/**
 * Owns one schedule that becomes effective at an immutable recurrence identity.
 */
final readonly class ScheduleSegment {
	/**
	 * Create one validated schedule segment.
	 *
	 * @param int                  $id         Stable non-negative internal segment ID.
	 * @param string               $anchor     Original generated recurrence identity where this segment starts.
	 * @param EventDateRange       $template   Effective first occurrence date range.
	 * @param RecurrenceDefinition $definition Factory-validated recurrence schedule.
	 * @throws InvalidArgumentException When identity or definition values are unsupported.
	 */
	public function __construct(
		public int $id,
		public string $anchor,
		public EventDateRange $template,
		public RecurrenceDefinition $definition
	) {
		if ( $this->id < 0 ) {
			throw new InvalidArgumentException( 'A recurrence segment ID cannot be negative.' );
		}

		if ( ! OccurrenceIdentity::is_generated_recurrence_id( $this->anchor ) ) {
			throw new InvalidArgumentException( 'A recurrence segment requires a generated anchor identity.' );
		}

		if ( ! $this->definition instanceof RecurrenceRule && ! $this->definition instanceof SpecificDatesSchedule ) {
			throw new InvalidArgumentException( 'A recurrence segment definition is unsupported.' );
		}
	}
}
