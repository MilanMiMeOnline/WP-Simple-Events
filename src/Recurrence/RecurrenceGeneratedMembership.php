<?php
/**
 * Generated occurrence membership.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;

/**
 * Proves generated identity membership through the canonical bounded engine.
 */
final readonly class RecurrenceGeneratedMembership {
	/**
	 * Create the membership service.
	 *
	 * @param RecurrenceEngine $engine Deterministic recurrence engine.
	 */
	public function __construct(
		private RecurrenceEngine $engine = new DeterministicRecurrenceEngine()
	) {}

	/**
	 * Determine whether one generated identity belongs to an aggregate schedule.
	 *
	 * Exceptions and manual occurrences do not affect structural membership.
	 *
	 * @param RecurrenceAggregate $aggregate Complete recurrence aggregate.
	 * @param string              $identity  Candidate generated identity.
	 * @throws InvalidArgumentException When identity or generation is unsafe.
	 */
	public function belongs( RecurrenceAggregate $aggregate, string $identity ): bool {
		if ( ! OccurrenceIdentity::is_generated_recurrence_id( $identity ) ) {
			throw new InvalidArgumentException( 'Generated membership requires one canonical generated identity.' );
		}

		$segment = $this->active_segment( $aggregate->segments, $identity );

		if ( null === $segment ) {
			return false;
		}

		if ( $segment->anchor === $identity ) {
			return true;
		}

		$date = substr( $identity, 0, 10 );

		try {
			$slots = $this->engine->generate(
				$segment->template,
				$segment->definition,
				RecurrenceGenerationWindow::between( $date, $date, 2 )
			)->slots();
		} catch ( RecurrenceGenerationException ) {
			throw new InvalidArgumentException( 'Generated occurrence membership could not be validated safely.' );
		}

		foreach ( $slots as $slot ) {
			$slot_identity = $slot->recurrence_id() === $segment->template->start_local()
				? $segment->anchor
				: $slot->recurrence_id();

			if ( $slot_identity === $identity ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return the chronological segment active at one identity.
	 *
	 * @param array  $segments Complete chronological segments.
	 * @param string $identity Generated identity.
	 * @phpstan-param list<ScheduleSegment> $segments
	 */
	private function active_segment( array $segments, string $identity ): ?ScheduleSegment {
		$active = null;

		foreach ( $segments as $segment ) {
			if ( strcmp( $segment->anchor, $identity ) > 0 ) {
				break;
			}

			$active = $segment;
		}

		return $active;
	}
}
