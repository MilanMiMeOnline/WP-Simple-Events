<?php
/**
 * This-and-following exception reconciliation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\RecurrenceOccurrenceBuilder;

/**
 * Detaches future exceptions that no longer belong to a replacement schedule.
 */
final readonly class RecurrenceFollowingExceptionReconciler {
	/**
	 * Create the bounded exception reconciler.
	 *
	 * @param RecurrenceGeneratedMembership $membership Generated membership validator.
	 * @param RecurrenceOccurrenceBuilder   $builder Effective current occurrence resolver.
	 * @param RecurrenceAggregateCodec      $codec   Canonical structural comparator.
	 */
	public function __construct(
		private RecurrenceGeneratedMembership $membership = new RecurrenceGeneratedMembership(),
		private RecurrenceOccurrenceBuilder $builder = new RecurrenceOccurrenceBuilder(),
		private RecurrenceAggregateCodec $codec = new RecurrenceAggregateCodec()
	) {}

	/**
	 * Preserve every future exception under its immutable identity.
	 *
	 * The proposed aggregate must differ structurally only in its segments. An
	 * exception orphaned by those segments becomes a detached manual occurrence;
	 * its override and reversible exclusion remain canonical and unchanged.
	 *
	 * @param RecurrenceAggregate $current       Current canonical aggregate.
	 * @param RecurrenceAggregate $proposed      Structurally replaced future schedule.
	 * @param string              $target        Selected generated split identity.
	 * @param EventStatus         $series_status Inherited canonical series status.
	 * @throws InvalidArgumentException When input drift or bounded reconciliation fails.
	 */
	public function reconcile(
		RecurrenceAggregate $current,
		RecurrenceAggregate $proposed,
		string $target,
		EventStatus $series_status
	): RecurrenceAggregate {
		if ( $current->series_uid !== $proposed->series_uid
			|| $current->timezone !== $proposed->timezone
			|| ! OccurrenceIdentity::is_generated_recurrence_id( $target )
		) {
			throw new InvalidArgumentException( 'A following reconciliation requires one unchanged series and generated target.' );
		}

		$this->assert_only_segments_changed( $current, $proposed );

		$manuals    = $proposed->manuals;
		$manual_ids = $this->manual_ids( $manuals );

		foreach ( $this->future_exception_identities( $current, $target ) as $identity ) {
			if ( isset( $manual_ids[ $identity ] ) || $this->membership->belongs( $proposed, $identity ) ) {
				continue;
			}

			$occurrence              = $this->current_occurrence_without_exclusion( $current, $identity, $series_status );
			$manuals[]               = new ManualOccurrence( $identity, $occurrence->date_range, $occurrence->status );
			$manual_ids[ $identity ] = true;
		}

		return RecurrenceAggregate::create(
			$proposed->series_uid,
			$proposed->timezone,
			$proposed->segments,
			$manuals,
			$proposed->exclusions,
			$proposed->overrides
		);
	}

	/**
	 * Require the structural mutation to preserve every exception collection.
	 *
	 * @param RecurrenceAggregate $current  Current canonical aggregate.
	 * @param RecurrenceAggregate $proposed Structurally changed proposal.
	 * @throws InvalidArgumentException When an exception changed before reconciliation.
	 */
	private function assert_only_segments_changed(
		RecurrenceAggregate $current,
		RecurrenceAggregate $proposed
	): void {
		$current_shape  = $this->codec->encode( $current );
		$proposed_shape = $this->codec->encode( $proposed );

		foreach ( array( 'manuals', 'exclusions', 'overrides' ) as $collection ) {
			if ( $current_shape[ $collection ] !== $proposed_shape[ $collection ] ) {
				throw new InvalidArgumentException( 'Following reconciliation cannot accept a pre-mutated exception collection.' );
			}
		}
	}

	/**
	 * Index existing manual and already-detached generated identities.
	 *
	 * @param array $manuals Canonical manual collection.
	 * @phpstan-param list<ManualOccurrence> $manuals
	 * @return array<string, true>
	 */
	private function manual_ids( array $manuals ): array {
		$ids = array();

		foreach ( $manuals as $manual ) {
			$ids[ $manual->recurrence_id ] = true;
		}

		return $ids;
	}

	/**
	 * Return unique generated exception identities at or after the split target.
	 *
	 * @param RecurrenceAggregate $aggregate Current canonical aggregate.
	 * @param string              $target    Selected split identity.
	 * @return list<string>
	 */
	private function future_exception_identities( RecurrenceAggregate $aggregate, string $target ): array {
		$identities = array();

		foreach ( array_merge( $aggregate->exclusions, $aggregate->overrides ) as $exception ) {
			$identity = $exception->recurrence_id;

			if ( OccurrenceIdentity::is_generated_recurrence_id( $identity ) && strcmp( $identity, $target ) >= 0 ) {
				$identities[ $identity ] = true;
			}
		}

		$identities = array_keys( $identities );
		sort( $identities, SORT_STRING );

		return $identities;
	}

	/**
	 * Resolve one current effective occurrence while ignoring its exclusion only.
	 *
	 * This keeps sparse date/status overrides intact and yields the state that a
	 * reversible detached skip or cancellation should reveal when restored.
	 *
	 * @param RecurrenceAggregate $current       Current canonical aggregate.
	 * @param string              $identity      Exception identity being detached.
	 * @param EventStatus         $series_status Canonical inherited event status.
	 * @throws InvalidArgumentException When the current exception cannot be resolved safely.
	 */
	private function current_occurrence_without_exclusion(
		RecurrenceAggregate $current,
		string $identity,
		EventStatus $series_status
	): EventOccurrence {
		$baseline = RecurrenceAggregate::create(
			$current->series_uid,
			$current->timezone,
			$current->segments,
			$current->manuals,
			array_values(
				array_filter(
					$current->exclusions,
					static fn ( OccurrenceExclusion $exclusion ): bool => $exclusion->recurrence_id !== $identity
				)
			),
			$current->overrides
		);

		$date = $this->effective_date( $current, $identity );

		try {
			$occurrences = $this->builder->build(
				1,
				$baseline,
				$series_status,
				RecurrenceGenerationWindow::between( $date, $date, RecurrenceGenerationWindow::MAX_ROWS ),
				1
			);
		} catch ( InvalidArgumentException ) {
			throw new InvalidArgumentException( 'A future exception could not be resolved before detaching it.' );
		}

		foreach ( $occurrences as $occurrence ) {
			if ( $occurrence->identity->recurrence_id() === $identity ) {
				return $occurrence;
			}
		}

		throw new InvalidArgumentException( 'A future exception has no current occurrence to preserve.' );
	}

	/**
	 * Resolve the current effective date used for one bounded lookup.
	 *
	 * @param RecurrenceAggregate $aggregate Current canonical aggregate.
	 * @param string              $identity  Exception identity.
	 */
	private function effective_date( RecurrenceAggregate $aggregate, string $identity ): string {
		foreach ( $aggregate->overrides as $override ) {
			if ( $override->recurrence_id !== $identity ) {
				continue;
			}

			$range = $override->fields()[ OccurrenceOverride::DATE_RANGE ] ?? null;

			if ( $range instanceof EventDateRange ) {
				return substr( $range->start_local(), 0, 10 );
			}
		}

		return substr( $identity, 0, 10 );
	}
}
