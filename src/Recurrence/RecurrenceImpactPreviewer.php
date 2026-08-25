<?php
/**
 * Bounded recurrence impact comparison.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\RecurrenceOccurrenceBuilder;

/**
 * Compares current and proposed complete projections through one shared builder.
 */
final readonly class RecurrenceImpactPreviewer {
	/**
	 * Create the preview service.
	 *
	 * @param RecurrenceOccurrenceBuilder $builder Qualified projection builder.
	 * @param RecurrenceAggregateCodec    $codec   Canonical structural comparator.
	 */
	public function __construct(
		private RecurrenceOccurrenceBuilder $builder = new RecurrenceOccurrenceBuilder(),
		private RecurrenceAggregateCodec $codec = new RecurrenceAggregateCodec()
	) {}

	/**
	 * Compare one current and proposed aggregate inside one explicit bounded window.
	 *
	 * @param RecurrenceAggregate        $current       Current canonical aggregate.
	 * @param RecurrenceAggregate        $proposed      Proposed complete aggregate.
	 * @param EventStatus                $series_status Canonical inherited status.
	 * @param RecurrenceGenerationWindow $window        Explicit comparison window.
	 * @param RecurrenceEditScope        $scope         Scope selected before editing.
	 * @param string|null                $target        Selected occurrence identity.
	 * @throws InvalidArgumentException When identity, scope or projection is inconsistent.
	 */
	public function preview(
		RecurrenceAggregate $current,
		RecurrenceAggregate $proposed,
		EventStatus $series_status,
		RecurrenceGenerationWindow $window,
		RecurrenceEditScope $scope,
		?string $target = null
	): RecurrenceImpactPreview {
		if ( $current->series_uid !== $proposed->series_uid || $current->timezone !== $proposed->timezone ) {
			throw new InvalidArgumentException( 'A recurrence preview cannot change series identity or timezone.' );
		}

		$current_shape  = $this->codec->encode( $current );
		$proposed_shape = $this->codec->encode( $proposed );
		$this->validate_scope( $scope, $target, $current_shape, $proposed_shape );

		$before            = $this->index(
			$this->builder->build( 1, $current, $series_status, $window, 1 )
		);
		$after             = $this->index(
			$this->builder->build( 1, $proposed, $series_status, $window, 2 )
		);
		$exception_changes = $this->changed_exception_identities( $current_shape, $proposed_shape );
		$identities        = array_unique( array_merge( array_keys( $before ), array_keys( $after ), array_keys( $exception_changes ) ) );
		$items             = array();

		foreach ( $identities as $identity ) {
			$current_occurrence  = $before[ $identity ] ?? null;
			$proposed_occurrence = $after[ $identity ] ?? null;
			$changes             = $this->changes( $current_occurrence, $proposed_occurrence );
			$exception_affected  = isset( $exception_changes[ $identity ] );

			if ( array() === $changes && ! $exception_affected ) {
				continue;
			}

			$reference = $proposed_occurrence ?? $current_occurrence;

			if ( ! $reference instanceof EventOccurrence ) {
				throw new InvalidArgumentException( 'Changed exception state has no bounded occurrence projection.' );
			}

			$items[] = new RecurrenceImpactItem(
				$identity,
				$reference->identity->public_key(),
				$changes,
				$exception_affected,
				$current_occurrence,
				$proposed_occurrence
			);
		}

		usort(
			$items,
			static fn ( RecurrenceImpactItem $left, RecurrenceImpactItem $right ): int => array(
				$left->sort_timestamp(),
				$left->public_key,
			) <=> array(
				$right->sort_timestamp(),
				$right->public_key,
			)
		);

		$this->validate_impact_scope( $scope, $target, $items );

		return new RecurrenceImpactPreview( $scope, $target, $items );
	}

	/**
	 * Index complete rows by immutable recurrence identity.
	 *
	 * @param array $occurrences Complete occurrence rows.
	 * @phpstan-param list<EventOccurrence> $occurrences
	 * @return array<string, EventOccurrence>
	 */
	private function index( array $occurrences ): array {
		$indexed = array();

		foreach ( $occurrences as $occurrence ) {
			$indexed[ $occurrence->identity->recurrence_id() ] = $occurrence;
		}

		return $indexed;
	}

	/**
	 * Compute visible effective changes for one identity.
	 *
	 * @param EventOccurrence|null $before Current occurrence.
	 * @param EventOccurrence|null $after  Proposed occurrence.
	 * @return list<RecurrenceImpactChange>
	 */
	private function changes( ?EventOccurrence $before, ?EventOccurrence $after ): array {
		if ( null === $before ) {
			return null === $after ? array() : array( RecurrenceImpactChange::ADDED );
		}

		if ( null === $after ) {
			return array( RecurrenceImpactChange::REMOVED );
		}

		$changes = array();

		if ( $this->range_signature( $before ) !== $this->range_signature( $after ) ) {
			$changes[] = RecurrenceImpactChange::MOVED;
		}

		if ( $before->status !== $after->status ) {
			$changes[] = RecurrenceImpactChange::STATUS_CHANGED;
		}

		if ( $before->source !== $after->source ) {
			$changes[] = RecurrenceImpactChange::SOURCE_CHANGED;
		}

		return $changes;
	}

	/**
	 * Return exact effective range fields.
	 *
	 * @param EventOccurrence $occurrence Effective occurrence.
	 * @return array{string, string, string, bool}
	 */
	private function range_signature( EventOccurrence $occurrence ): array {
		return array(
			$occurrence->date_range->start_local(),
			$occurrence->date_range->end_local(),
			$occurrence->date_range->timezone(),
			$occurrence->date_range->all_day(),
		);
	}

	/**
	 * Compare exact canonical manual, exclusion and override state by identity.
	 *
	 * @param array<string, mixed> $current  Current canonical aggregate shape.
	 * @param array<string, mixed> $proposed Proposed canonical aggregate shape.
	 * @return array<string, true>
	 */
	private function changed_exception_identities( array $current, array $proposed ): array {
		$before     = $this->exception_states( $current );
		$after      = $this->exception_states( $proposed );
		$identities = array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) );
		$changed    = array();

		foreach ( $identities as $identity ) {
			if ( ( $before[ $identity ] ?? null ) !== ( $after[ $identity ] ?? null ) ) {
				$changed[ $identity ] = true;
			}
		}

		return $changed;
	}

	/**
	 * Index canonical exception fragments by recurrence identity.
	 *
	 * @param array<string, mixed> $shape Canonical aggregate shape.
	 * @return array<string, array<string, mixed>>
	 * @throws InvalidArgumentException When canonical exception state has an invalid shape.
	 */
	private function exception_states( array $shape ): array {
		$states = array();

		foreach ( array( 'manuals', 'exclusions', 'overrides' ) as $collection ) {
			$items = $shape[ $collection ] ?? array();

			if ( ! is_array( $items ) ) {
				throw new InvalidArgumentException( 'A canonical recurrence exception collection is invalid.' );
			}

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || ! is_string( $item['recurrence_id'] ?? null ) ) {
					throw new InvalidArgumentException( 'A canonical recurrence exception is invalid.' );
				}

				$states[ $item['recurrence_id'] ][ $collection ] = $item;
			}
		}

		return $states;
	}

	/**
	 * Reject structural edits outside the selected scope before expansion.
	 *
	 * @param RecurrenceEditScope  $scope    Selected scope.
	 * @param string|null          $target   Selected identity.
	 * @param array<string, mixed> $current  Current canonical shape.
	 * @param array<string, mixed> $proposed Proposed canonical shape.
	 * @throws InvalidArgumentException When the mutation falls outside its selected scope.
	 */
	private function validate_scope(
		RecurrenceEditScope $scope,
		?string $target,
		array $current,
		array $proposed
	): void {
		if ( RecurrenceEditScope::COMPLETE_SERIES === $scope ) {
			return;
		}

		if ( null === $target || ! OccurrenceIdentity::valid_recurrence_id( $target ) || 'one-off' === $target ) {
			throw new InvalidArgumentException( 'The selected recurrence scope requires one canonical occurrence identity.' );
		}

		if ( RecurrenceEditScope::ONLY_THIS === $scope ) {
			if ( ( $current['segments'] ?? null ) !== ( $proposed['segments'] ?? null ) ) {
				throw new InvalidArgumentException( 'Only-this edits cannot change a schedule segment.' );
			}

			$changes = $this->changed_exception_identities( $current, $proposed );

			if ( array() !== array_diff( array_keys( $changes ), array( $target ) ) ) {
				throw new InvalidArgumentException( 'Only-this edits cannot change another occurrence.' );
			}

			return;
		}

		if ( ! OccurrenceIdentity::is_generated_recurrence_id( $target ) ) {
			throw new InvalidArgumentException( 'This-and-following requires a generated occurrence identity.' );
		}

		$this->validate_prior_segments( $target, $current, $proposed );
		$this->validate_prior_exceptions( $target, $current, $proposed );
	}

	/**
	 * Require every segment before the target to remain byte-for-byte canonical.
	 *
	 * @param string               $target   Selected generated identity.
	 * @param array<string, mixed> $current  Current shape.
	 * @param array<string, mixed> $proposed Proposed shape.
	 * @throws InvalidArgumentException When canonical segments are invalid or prior segments changed.
	 */
	private function validate_prior_segments( string $target, array $current, array $proposed ): void {
		$current_segments  = $current['segments'] ?? null;
		$proposed_segments = $proposed['segments'] ?? null;

		if ( ! is_array( $current_segments ) || ! is_array( $proposed_segments ) ) {
			throw new InvalidArgumentException( 'Canonical recurrence segments are invalid.' );
		}

		$current_prior  = $this->prior_segments( $target, $current_segments );
		$proposed_prior = $this->prior_segments( $target, $proposed_segments );

		if ( $current_prior !== $proposed_prior ) {
			throw new InvalidArgumentException( 'This-and-following cannot change an earlier schedule segment.' );
		}
	}

	/**
	 * Return canonical segments anchored before the selected identity.
	 *
	 * @param string $target   Selected generated identity.
	 * @param array  $segments Canonical segments.
	 * @phpstan-param array<array-key, mixed> $segments
	 * @return list<array<string, mixed>>
	 */
	private function prior_segments( string $target, array $segments ): array {
		return array_values(
			array_filter(
				$segments,
				static fn ( mixed $segment ): bool => is_array( $segment )
					&& is_string( $segment['anchor'] ?? null )
					&& strcmp( $segment['anchor'], $target ) < 0
			)
		);
	}

	/**
	 * Reject exception mutations whose immutable/effective date precedes the target.
	 *
	 * @param string               $target   Selected generated identity.
	 * @param array<string, mixed> $current  Current shape.
	 * @param array<string, mixed> $proposed Proposed shape.
	 * @throws InvalidArgumentException When an earlier exception would change.
	 */
	private function validate_prior_exceptions( string $target, array $current, array $proposed ): void {
		$changed = $this->changed_exception_identities( $current, $proposed );
		$before  = $this->exception_states( $current );
		$after   = $this->exception_states( $proposed );

		foreach ( array_keys( $changed ) as $identity ) {
			if ( OccurrenceIdentity::is_generated_recurrence_id( $identity ) && strcmp( $identity, $target ) < 0 ) {
				throw new InvalidArgumentException( 'This-and-following cannot change an earlier occurrence exception.' );
			}

			$state  = $after[ $identity ] ?? $before[ $identity ] ?? array();
			$manual = $state['manuals']['date_range']['start_local'] ?? null;

			if ( OccurrenceIdentity::is_manual_recurrence_id( $identity )
				&& is_string( $manual )
				&& substr( $manual, 0, 10 ) < substr( $target, 0, 10 )
			) {
				throw new InvalidArgumentException( 'This-and-following cannot change an earlier manual occurrence.' );
			}
		}
	}

	/**
	 * Final guard against generated impact leaking before a following-scope target.
	 *
	 * @param RecurrenceEditScope $scope  Selected scope.
	 * @param string|null         $target Selected identity.
	 * @param array               $items  Chronological preview items.
	 * @phpstan-param list<RecurrenceImpactItem> $items
	 * @throws InvalidArgumentException When generated impact leaks before the selected target.
	 */
	private function validate_impact_scope( RecurrenceEditScope $scope, ?string $target, array $items ): void {
		if ( RecurrenceEditScope::THIS_AND_FOLLOWING !== $scope || null === $target ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( OccurrenceIdentity::is_generated_recurrence_id( $item->recurrence_id )
				&& strcmp( $item->recurrence_id, $target ) < 0
			) {
				throw new InvalidArgumentException( 'This-and-following would alter an earlier generated occurrence.' );
			}
		}
	}
}
