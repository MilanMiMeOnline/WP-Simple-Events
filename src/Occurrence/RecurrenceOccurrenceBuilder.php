<?php
/**
 * Pure recurring event occurrence reconciliation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\DeterministicRecurrenceEngine;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEngine;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationResult;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceSlot;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;

/**
 * Reconciles segments and exceptions into one complete in-memory generation.
 */
final class RecurrenceOccurrenceBuilder {
	private const MAX_INBOUND_MOVES = 25;

	/**
	 * Create the recurring occurrence builder.
	 *
	 * @param RecurrenceEngine $engine Deterministic bounded rule engine.
	 */
	public function __construct(
		private readonly RecurrenceEngine $engine = new DeterministicRecurrenceEngine()
	) {}

	/**
	 * Reconcile every generated and manual identity into effective rows.
	 *
	 * @param int                        $event_id      Canonical event post ID.
	 * @param RecurrenceAggregate        $aggregate     Validated complete aggregate.
	 * @param EventStatus                $series_status Inherited event status.
	 * @param RecurrenceGenerationWindow $window        Explicit bounded projection window.
	 * @param int                        $generation    Positive inactive generation.
	 * @return list<EventOccurrence>
	 * @throws InvalidArgumentException When identities or exception relationships are inconsistent.
	 */
	public function build(
		int $event_id,
		RecurrenceAggregate $aggregate,
		EventStatus $series_status,
		RecurrenceGenerationWindow $window,
		int $generation
	): array {
		$exclusions = $this->exclusions_by_identity( $aggregate );
		$overrides  = $this->overrides_by_identity( $aggregate );
		$manual_ids = $this->manual_ids( $aggregate );
		$seen       = array();
		$rows       = array();
		$candidates = 0;

		foreach ( $aggregate->segments as $index => $segment ) {
			$this->validate_segment_seed( $segment );
			$this->validate_segment_anchor( $aggregate, $index );
			$next_anchor = $aggregate->segments[ $index + 1 ]->anchor ?? null;
			$through     = null !== $next_anchor && substr( $next_anchor, 0, 10 ) < $window->through_date()
				? substr( $next_anchor, 0, 10 )
				: $window->through_date();
			$remaining   = $window->max_rows() - $candidates;

			if ( $through < $window->from_date() ) {
				continue;
			}

			if ( $remaining < 1 ) {
				throw new InvalidArgumentException( 'The recurring projection exceeds its candidate bound.' );
			}

			$segment_window = RecurrenceGenerationWindow::between( $window->from_date(), $through, $remaining );
			$generated      = $this->safe_generate( $segment, $segment_window );

			foreach ( $generated->slots() as $slot ) {
				++$candidates;
				$is_seed       = $slot->recurrence_id() === $segment->template->start_local();
				$recurrence_id = $is_seed
					? $segment->anchor
					: $slot->recurrence_id();

				if ( ! $is_seed
					&& substr( $recurrence_id, 0, 10 ) <= substr( $segment->anchor, 0, 10 )
				) {
					continue;
				}

				if ( null !== $next_anchor && strcmp( $recurrence_id, $next_anchor ) >= 0 ) {
					continue;
				}

				if ( isset( $seen[ $recurrence_id ] ) ) {
					throw new InvalidArgumentException( 'Generated recurrence identities must be unique across segments.' );
				}

				$seen[ $recurrence_id ] = true;
				$exclusion              = $exclusions[ $recurrence_id ] ?? null;

				if ( OccurrenceExclusionAction::SKIP === $exclusion ) {
					continue;
				}

				$override = $overrides[ $recurrence_id ] ?? null;
				$range    = $this->effective_range( $slot->date_range(), $override );
				$status   = OccurrenceExclusionAction::CANCEL === $exclusion
					? EventStatus::CANCELLED
					: $this->effective_status( $series_status, $override );

				if ( $this->overlaps_window( $range, $window ) ) {
					$rows[] = $this->occurrence(
						$event_id,
						$aggregate->series_uid,
						$recurrence_id,
						$generation,
						$segment->id,
						OccurrenceSource::RULE,
						$range,
						$status
					);
				}
			}
		}

		$inbound_moves = 0;

		foreach ( $aggregate->overrides as $override ) {
			$override_range = $override->fields()[ OccurrenceOverride::DATE_RANGE ] ?? null;

			if ( ! $override_range instanceof EventDateRange
				|| ! OccurrenceIdentity::is_generated_recurrence_id( $override->recurrence_id )
				|| isset( $manual_ids[ $override->recurrence_id ] )
				|| isset( $seen[ $override->recurrence_id ] )
				|| ! $this->overlaps_window( $override_range, $window )
			) {
				continue;
			}

			++$inbound_moves;

			if ( $inbound_moves > self::MAX_INBOUND_MOVES ) {
				throw new InvalidArgumentException( 'Too many moved occurrences enter one projection window.' );
			}

			$source = $this->generated_source( $aggregate, $override->recurrence_id );

			if ( null === $source ) {
				throw new InvalidArgumentException( 'A moved occurrence no longer belongs to the schedule.' );
			}

			$seen[ $override->recurrence_id ] = true;
			$exclusion                        = $exclusions[ $override->recurrence_id ] ?? null;
			$status                           = OccurrenceExclusionAction::CANCEL === $exclusion
				? EventStatus::CANCELLED
				: $this->effective_status( $series_status, $override );
			$rows[]                           = $this->occurrence(
				$event_id,
				$aggregate->series_uid,
				$override->recurrence_id,
				$generation,
				$source['segment_id'],
				OccurrenceSource::RULE,
				$override_range,
				$status
			);
		}

		foreach ( $aggregate->manuals as $manual ) {
			if ( isset( $seen[ $manual->recurrence_id ] ) ) {
				throw new InvalidArgumentException( 'Manual and generated recurrence identities must be unique.' );
			}

			$seen[ $manual->recurrence_id ] = true;
			$override                       = $overrides[ $manual->recurrence_id ] ?? null;
			$exclusion                      = $exclusions[ $manual->recurrence_id ] ?? null;

			if ( OccurrenceExclusionAction::SKIP === $exclusion ) {
				continue;
			}

			$range  = $this->effective_range( $manual->date_range, $override );
			$status = OccurrenceExclusionAction::CANCEL === $exclusion
				? EventStatus::CANCELLED
				: $this->effective_status( $manual->status, $override );

			if ( $this->overlaps_window( $range, $window ) ) {
				$rows[] = $this->occurrence(
					$event_id,
					$aggregate->series_uid,
					$manual->recurrence_id,
					$generation,
					0,
					OccurrenceSource::MANUAL,
					$range,
					$status
				);
			}
		}

		$this->validate_window_exceptions( $exclusions, $overrides, $seen, $window );

		if ( count( $rows ) > $window->max_rows() ) {
			throw new InvalidArgumentException( 'The recurring projection exceeds its output-row bound.' );
		}

		usort(
			$rows,
			static fn ( EventOccurrence $left, EventOccurrence $right ): int => array(
				$left->date_range->start_utc(),
				$left->identity->public_key(),
			) <=> array(
				$right->date_range->start_utc(),
				$right->identity->public_key(),
			)
		);

		return $rows;
	}

	/**
	 * Index manual additions and detached generated identities.
	 *
	 * @param RecurrenceAggregate $aggregate Validated aggregate.
	 * @return array<string, true>
	 */
	private function manual_ids( RecurrenceAggregate $aggregate ): array {
		$indexed = array();

		foreach ( $aggregate->manuals as $manual ) {
			$indexed[ $manual->recurrence_id ] = true;
		}

		return $indexed;
	}

	/**
	 * Require each non-root anchor to be one generated slot of its predecessor.
	 *
	 * @param RecurrenceAggregate $aggregate Validated aggregate.
	 * @param int                 $index     Current chronological segment index.
	 * @throws InvalidArgumentException When a segment anchor is not a prior schedule slot.
	 */
	private function validate_segment_anchor( RecurrenceAggregate $aggregate, int $index ): void {
		if ( $index < 1 ) {
			return;
		}

		$segment  = $aggregate->segments[ $index ] ?? null;
		$previous = $aggregate->segments[ $index - 1 ] ?? null;

		if ( ! $segment instanceof ScheduleSegment || ! $previous instanceof ScheduleSegment ) {
			throw new InvalidArgumentException( 'A recurrence segment boundary is incomplete.' );
		}

		$date   = substr( $segment->anchor, 0, 10 );
		$result = $this->safe_generate(
			$previous,
			RecurrenceGenerationWindow::between( $date, $date, RecurrenceGenerationWindow::MAX_ROWS )
		);

		foreach ( $result->slots() as $slot ) {
			$identity = $slot->recurrence_id() === $previous->template->start_local()
				? $previous->anchor
				: $slot->recurrence_id();

			if ( $segment->anchor === $identity ) {
				return;
			}
		}

		throw new InvalidArgumentException( 'A recurrence segment anchor must belong to its preceding schedule.' );
	}

	/**
	 * Resolve one generated identity outside the main effective-date window.
	 *
	 * This bounded path exists only for individual date overrides moved into the
	 * requested window. Its small cap prevents aggregate-sized repeated catch-up.
	 *
	 * @param RecurrenceAggregate $aggregate     Validated aggregate.
	 * @param string              $recurrence_id Immutable generated identity.
	 * @return array{segment_id: int}|null
	 * @throws InvalidArgumentException When deterministic membership validation fails.
	 */
	private function generated_source( RecurrenceAggregate $aggregate, string $recurrence_id ): ?array {
		foreach ( $aggregate->segments as $index => $segment ) {
			$next_anchor = $aggregate->segments[ $index + 1 ]->anchor ?? null;

			if ( $recurrence_id === $segment->anchor ) {
				return array( 'segment_id' => $segment->id );
			}

			if ( substr( $recurrence_id, 0, 10 ) <= substr( $segment->anchor, 0, 10 )
				|| ( null !== $next_anchor && strcmp( $recurrence_id, $next_anchor ) >= 0 )
			) {
				continue;
			}

			$date      = substr( $recurrence_id, 0, 10 );
			$generated = $this->safe_generate(
				$segment,
				RecurrenceGenerationWindow::between(
					$date,
					$date,
					RecurrenceGenerationWindow::MAX_ROWS
				)
			);

			foreach ( $generated->slots() as $slot ) {
				if ( $slot->recurrence_id() === $recurrence_id ) {
					return array( 'segment_id' => $segment->id );
				}
			}
		}

		return null;
	}

	/**
	 * Require every segment definition to generate its own effective seed.
	 *
	 * @param ScheduleSegment $segment Validated schedule segment.
	 * @throws InvalidArgumentException When the definition skips its seed.
	 */
	private function validate_segment_seed( ScheduleSegment $segment ): void {
		$date   = substr( $segment->template->start_local(), 0, 10 );
		$result = $this->safe_generate( $segment, RecurrenceGenerationWindow::between( $date, $date, 1 ) );
		$first  = $result->slots()[0] ?? null;

		if ( ! $first instanceof RecurrenceSlot || $first->recurrence_id() !== $segment->template->start_local() ) {
			throw new InvalidArgumentException( 'A recurrence segment definition must include its effective seed.' );
		}
	}

	/**
	 * Normalize engine failures into one fail-closed reconciliation failure.
	 *
	 * @param ScheduleSegment            $segment Validated schedule segment.
	 * @param RecurrenceGenerationWindow $window  Explicit bounded projection window.
	 * @throws InvalidArgumentException When deterministic expansion cannot complete.
	 */
	private function safe_generate(
		ScheduleSegment $segment,
		RecurrenceGenerationWindow $window
	): RecurrenceGenerationResult {
		try {
			return $this->engine->generate( $segment->template, $segment->definition, $window );
		} catch ( RecurrenceGenerationException ) {
			throw new InvalidArgumentException( 'The recurring projection could not be generated safely.' );
		}
	}

	/**
	 * Index exclusions by immutable generated identity.
	 *
	 * @param RecurrenceAggregate $aggregate Validated aggregate.
	 * @return array<string, OccurrenceExclusionAction>
	 */
	private function exclusions_by_identity( RecurrenceAggregate $aggregate ): array {
		$indexed = array();

		foreach ( $aggregate->exclusions as $exclusion ) {
			$indexed[ $exclusion->recurrence_id ] = $exclusion->action;
		}

		return $indexed;
	}

	/**
	 * Index sparse overrides by immutable generated or manual identity.
	 *
	 * @param RecurrenceAggregate $aggregate Validated aggregate.
	 * @return array<string, OccurrenceOverride>
	 */
	private function overrides_by_identity( RecurrenceAggregate $aggregate ): array {
		$indexed = array();

		foreach ( $aggregate->overrides as $override ) {
			$indexed[ $override->recurrence_id ] = $override;
		}

		return $indexed;
	}

	/**
	 * Apply one sparse date-range override.
	 *
	 * @param EventDateRange          $inherited Inherited range.
	 * @param OccurrenceOverride|null $override Sparse override.
	 */
	private function effective_range( EventDateRange $inherited, ?OccurrenceOverride $override ): EventDateRange {
		$range = $override?->fields()[ OccurrenceOverride::DATE_RANGE ] ?? null;

		return $range instanceof EventDateRange ? $range : $inherited;
	}

	/**
	 * Apply one sparse event-status override.
	 *
	 * @param EventStatus             $inherited Inherited effective status.
	 * @param OccurrenceOverride|null $override Sparse override.
	 */
	private function effective_status( EventStatus $inherited, ?OccurrenceOverride $override ): EventStatus {
		$status = $override?->fields()[ OccurrenceOverride::STATUS ] ?? null;

		return $status instanceof EventStatus ? $status : $inherited;
	}

	/**
	 * Determine local calendar overlap with the explicit projection window.
	 *
	 * @param EventDateRange             $range  Effective occurrence range.
	 * @param RecurrenceGenerationWindow $window Projection window.
	 */
	private function overlaps_window( EventDateRange $range, RecurrenceGenerationWindow $window ): bool {
		return substr( $range->start_local(), 0, 10 ) <= $window->through_date()
			&& substr( $range->end_local(), 0, 10 ) >= $window->from_date();
	}

	/**
	 * Reject in-window generated exceptions that no longer belong to a schedule.
	 *
	 * @param array<string, OccurrenceExclusionAction> $exclusions Indexed exclusions.
	 * @param array<string, OccurrenceOverride>        $overrides  Indexed overrides.
	 * @param array<string, true>                      $seen       Generated/manual identities seen.
	 * @param RecurrenceGenerationWindow               $window     Projection window.
	 * @throws InvalidArgumentException When an in-window generated identity is orphaned.
	 */
	private function validate_window_exceptions(
		array $exclusions,
		array $overrides,
		array $seen,
		RecurrenceGenerationWindow $window
	): void {
		$identities = array_unique( array_merge( array_keys( $exclusions ), array_keys( $overrides ) ) );

		foreach ( $identities as $identity ) {
			$date = substr( $identity, 0, 10 );

			if ( ! OccurrenceIdentity::is_generated_recurrence_id( $identity )
				|| $date < $window->from_date()
				|| $date > $window->through_date()
			) {
				continue;
			}

			if ( ! isset( $seen[ $identity ] ) ) {
				throw new InvalidArgumentException( 'An in-window occurrence exception no longer belongs to the schedule.' );
			}
		}
	}

	/**
	 * Create one complete validated projection row.
	 *
	 * @param int              $event_id      Canonical event post ID.
	 * @param string           $series_uid    Immutable series UUID.
	 * @param string           $recurrence_id Immutable occurrence identity.
	 * @param int              $generation    Positive inactive generation.
	 * @param int              $segment_id    Source segment ID.
	 * @param OccurrenceSource $source        Generated or manual source.
	 * @param EventDateRange   $range         Effective date range.
	 * @param EventStatus      $status        Effective status.
	 */
	private function occurrence(
		int $event_id,
		string $series_uid,
		string $recurrence_id,
		int $generation,
		int $segment_id,
		OccurrenceSource $source,
		EventDateRange $range,
		EventStatus $status
	): EventOccurrence {
		return new EventOccurrence(
			$event_id,
			OccurrenceIdentity::from( $series_uid, $recurrence_id ),
			$generation,
			$segment_id,
			$source,
			$range,
			$status
		);
	}
}
