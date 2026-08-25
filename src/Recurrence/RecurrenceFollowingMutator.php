<?php
/**
 * This-and-following schedule mutation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;

/**
 * Replaces the complete schedule future at one immutable generated boundary.
 */
final readonly class RecurrenceFollowingMutator {
	/**
	 * Create the structural recurrence mutator.
	 *
	 * @param RecurrenceGeneratedMembership $membership Deterministic target-membership validator.
	 * @param RecurrenceEngine              $engine     Replacement seed validator.
	 */
	public function __construct(
		private RecurrenceGeneratedMembership $membership = new RecurrenceGeneratedMembership(),
		private RecurrenceEngine $engine = new DeterministicRecurrenceEngine()
	) {}

	/**
	 * Replace the schedule at and after one non-root generated occurrence.
	 *
	 * Earlier segments and every exception collection remain unchanged. A later
	 * reconciliation step owns exceptions whose generated membership disappears.
	 *
	 * @param RecurrenceAggregate  $current    Current complete canonical aggregate.
	 * @param string               $target     Immutable generated split identity.
	 * @param EventDateRange       $template   Effective first range of the replacement segment.
	 * @param RecurrenceDefinition $definition Complete replacement recurrence definition.
	 * @throws InvalidArgumentException When the boundary or replacement schedule is invalid.
	 */
	public function replace_from(
		RecurrenceAggregate $current,
		string $target,
		EventDateRange $template,
		RecurrenceDefinition $definition
	): RecurrenceAggregate {
		$root = $current->segments[0] ?? null;

		if ( ! $root instanceof ScheduleSegment
			|| ! OccurrenceIdentity::is_generated_recurrence_id( $target )
			|| strcmp( $target, $root->anchor ) <= 0
		) {
			throw new InvalidArgumentException( 'This-and-following requires a generated occurrence after the series root.' );
		}

		if ( $template->timezone() !== $current->timezone ) {
			throw new InvalidArgumentException( 'A following schedule must retain the series timezone.' );
		}

		$active   = $this->active_segment( $current->segments, $target );
		$existing = $active->anchor === $target ? $active : null;

		if ( null === $existing && ! $this->membership->belongs( $current, $target ) ) {
			throw new InvalidArgumentException( 'The selected following boundary does not belong to the current schedule.' );
		}

		$this->assert_definition_generates_seed( $template, $definition );

		$segment_id = null !== $existing
			? $existing->id
			: $this->next_segment_id( $current->segments );
		$segments   = array_values(
			array_filter(
				$current->segments,
				static fn ( ScheduleSegment $segment ): bool => strcmp( $segment->anchor, $target ) < 0
			)
		);
		$segments[] = new ScheduleSegment( $segment_id, $target, $template, $definition );

		return RecurrenceAggregate::create(
			$current->series_uid,
			$current->timezone,
			$segments,
			$current->manuals,
			$current->exclusions,
			$current->overrides
		);
	}

	/**
	 * Resolve the segment active at one chronological generated identity.
	 *
	 * @param array  $segments Complete chronological segment collection.
	 * @param string $target   Generated target identity.
	 * @phpstan-param list<ScheduleSegment> $segments
	 * @throws InvalidArgumentException When no segment owns the target boundary.
	 */
	private function active_segment( array $segments, string $target ): ScheduleSegment {
		$active = null;

		foreach ( $segments as $segment ) {
			if ( strcmp( $segment->anchor, $target ) > 0 ) {
				break;
			}

			$active = $segment;
		}

		if ( ! $active instanceof ScheduleSegment ) {
			throw new InvalidArgumentException( 'The selected following boundary has no active schedule segment.' );
		}

		return $active;
	}

	/**
	 * Require a replacement definition to include its own effective seed.
	 *
	 * @param EventDateRange       $template   Replacement first occurrence.
	 * @param RecurrenceDefinition $definition Replacement recurrence definition.
	 * @throws InvalidArgumentException When the definition is unsafe or omits its seed.
	 */
	private function assert_definition_generates_seed(
		EventDateRange $template,
		RecurrenceDefinition $definition
	): void {
		$date = substr( $template->start_local(), 0, 10 );

		try {
			$first = $this->engine->generate(
				$template,
				$definition,
				RecurrenceGenerationWindow::between( $date, $date, 1 )
			)->slots()[0] ?? null;
		} catch ( RecurrenceGenerationException ) {
			throw new InvalidArgumentException( 'The replacement following schedule could not be generated safely.' );
		}

		if ( null === $first || $first->recurrence_id() !== $template->start_local() ) {
			throw new InvalidArgumentException( 'The replacement following schedule must include its first occurrence.' );
		}
	}

	/**
	 * Allocate a monotonic segment ID without reusing removed future IDs.
	 *
	 * @param array $segments Complete segment collection before replacement.
	 * @phpstan-param list<ScheduleSegment> $segments
	 * @throws InvalidArgumentException When no new integer ID can be allocated.
	 */
	private function next_segment_id( array $segments ): int {
		$maximum = null;

		foreach ( $segments as $segment ) {
			$maximum = null === $maximum || $segment->id > $maximum ? $segment->id : $maximum;
		}

		if ( null === $maximum ) {
			throw new InvalidArgumentException( 'A following schedule requires an existing segment.' );
		}

		if ( PHP_INT_MAX === $maximum ) {
			throw new InvalidArgumentException( 'The recurrence segment identifier range is exhausted.' );
		}

		return $maximum + 1;
	}
}
