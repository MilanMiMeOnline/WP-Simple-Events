<?php
/**
 * WordPress metadata criteria for occurrence coverage maintenance.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Content\EventMeta;

/**
 * Builds bounded candidate predicates from the central window policy.
 */
final readonly class OccurrenceCoverageMetaQuery {
	/**
	 * Create the criteria builder.
	 *
	 * @param OccurrenceProjectionWindowPolicy $policy Projection horizon policy.
	 */
	public function __construct(
		private OccurrenceProjectionWindowPolicy $policy = new OccurrenceProjectionWindowPolicy()
	) {}

	/**
	 * Return gaps that must fail the public occurrence-read readiness gate.
	 *
	 * @param string $today Canonical WordPress-local current date.
	 * @return array<string|int, mixed>
	 */
	public function public_gaps( string $today ): array {
		return $this->criteria( $today, $this->policy->minimum_through( $today ), true );
	}

	/**
	 * Split public existence checks to avoid a multiplicative metadata join tree.
	 *
	 * The combined predicate remains authoritative for candidate-page consumers.
	 * These five disjuncts retain exactly its start and recurrence restrictions.
	 *
	 * @param string $today Canonical WordPress-local current date.
	 * @return list<array<string|int, mixed>>
	 */
	public function public_gap_checks( string $today ): array {
		$combined  = $this->public_gaps( $today );
		$start     = $combined[0];
		$gaps      = $combined[1];
		$recurring = $gaps[2];
		$checks    = array(
			array(
				'relation' => 'AND',
				$start,
				$gaps[0],
			),
			array(
				'relation' => 'AND',
				$start,
				$gaps[1],
			),
		);

		// Missing/invalid pairs refer to one metadata key each, keeping joins small.
		foreach ( array( 0, 2, 4 ) as $offset ) {
			$checks[] = array(
				'relation' => 'AND',
				$start,
				$recurring[0],
				array(
					'relation' => 'OR',
					$recurring[1][ $offset ],
					$recurring[1][ $offset + 1 ],
				),
			);
		}

		return $checks;
	}

	/**
	 * Return recurring projections due for buffered background renewal.
	 *
	 * @param string $today Canonical WordPress-local current date.
	 * @return array<string|int, mixed>
	 */
	public function renewal_due( string $today ): array {
		return $this->criteria( $today, $this->policy->renewal_through( $today ), false );
	}

	/**
	 * Build either all public gaps or recurring-only buffered renewal candidates.
	 *
	 * @param string $today                Canonical current date.
	 * @param string $through              Required inclusive coverage end.
	 * @param bool   $include_one_off_gaps Whether missing one-off indexes are candidates.
	 * @return array<string|int, mixed>
	 */
	private function criteria( string $today, string $through, bool $include_one_off_gaps ): array {
		$recurring_coverage_gap = array(
			'relation' => 'AND',
			array(
				'key'     => EventMeta::RECURRENCE,
				'value'   => '',
				'compare' => '!=',
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => EventMeta::COVERAGE_FROM,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => EventMeta::COVERAGE_FROM,
					'value'   => $today,
					'compare' => '>',
					'type'    => 'DATE',
				),
				array(
					'key'     => EventMeta::COVERAGE_THROUGH,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => EventMeta::COVERAGE_THROUGH,
					'value'   => $through,
					'compare' => '<',
					'type'    => 'DATE',
				),
				array(
					'key'     => EventMeta::COVERAGE_GENERATION,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => EventMeta::COVERAGE_GENERATION,
					'value'   => 0,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
			),
		);

		$gaps = array(
			'relation' => 'OR',
			array(
				'key'     => EventMeta::ACTIVE_GENERATION,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => EventMeta::INDEX_DIRTY,
				'compare' => 'EXISTS',
			),
			$recurring_coverage_gap,
		);

		if ( ! $include_one_off_gaps ) {
			$gaps = array(
				'relation' => 'AND',
				array(
					'key'     => EventMeta::RECURRENCE,
					'value'   => '',
					'compare' => '!=',
				),
				$gaps,
			);
		}

		return array(
			'relation' => 'AND',
			array(
				'key'     => EventMeta::START_LOCAL,
				'value'   => '',
				'compare' => '!=',
			),
			$gaps,
		);
	}
}
