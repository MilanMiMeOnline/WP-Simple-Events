<?php
/**
 * Compact admin recurrence summary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\ConcurrentRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFrequency;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\SpecificDatesSchedule;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use RuntimeException;

/**
 * Describes canonical recurrence without exposing protected aggregate data.
 */
final readonly class AdminRecurrenceSummary {
	/**
	 * Create the summary boundary.
	 *
	 * @param ConcurrentRecurrenceAggregateStore $store Protected aggregate store.
	 */
	public function __construct(
		private ConcurrentRecurrenceAggregateStore $store = new WordPressRecurrenceAggregateStore()
	) {}

	/**
	 * Return one translated, text-only list-table summary.
	 *
	 * Corrupt state is deliberately distinguished from a one-off event without
	 * echoing decoder or storage errors into wp-admin.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function summarize( int $event_id ): string {
		try {
			$aggregate = $this->store->load( $event_id );
		} catch ( InvalidArgumentException | RuntimeException ) {
			return __( 'Recurrence unavailable', 'mime-simple-events-calendar' );
		}

		if ( null === $aggregate ) {
			return __( 'One-off event', 'mime-simple-events-calendar' );
		}

		$definition = $aggregate->segments[0]->definition;

		if ( $definition instanceof SpecificDatesSchedule ) {
			$summary = sprintf(
				/* translators: %d: number of explicitly selected event dates. */
				__( 'Selected dates · %d events', 'mime-simple-events-calendar' ),
				count( $definition->dates() )
			);
		} elseif ( $definition instanceof RecurrenceRule ) {
			$summary = $this->rule( $definition );
		} else {
			return __( 'Recurrence unavailable', 'mime-simple-events-calendar' );
		}

		if ( count( $aggregate->segments ) > 1 ) {
			$summary .= ' · ' . sprintf(
				/* translators: %d: number of chronological schedule segments. */
				__( '%d schedule segments', 'mime-simple-events-calendar' ),
				count( $aggregate->segments )
			);
		}

		return $summary;
	}

	/**
	 * Describe one generated rule and its termination.
	 *
	 * @param RecurrenceRule $rule Validated generated recurrence rule.
	 */
	private function rule( RecurrenceRule $rule ): string {
		$unit = match ( $rule->frequency() ) {
			RecurrenceFrequency::DAILY => array(
				__( 'day', 'mime-simple-events-calendar' ),
				__( 'days', 'mime-simple-events-calendar' ),
			),
			RecurrenceFrequency::WEEKLY => array(
				__( 'week', 'mime-simple-events-calendar' ),
				__( 'weeks', 'mime-simple-events-calendar' ),
			),
			RecurrenceFrequency::MONTHLY => array(
				__( 'month', 'mime-simple-events-calendar' ),
				__( 'months', 'mime-simple-events-calendar' ),
			),
			RecurrenceFrequency::YEARLY => array(
				__( 'year', 'mime-simple-events-calendar' ),
				__( 'years', 'mime-simple-events-calendar' ),
			),
		};
		$interval = $rule->interval();
		$summary  = 1 === $interval
			? sprintf(
				/* translators: %s: recurrence unit, for example week. */
				__( 'Every %s', 'mime-simple-events-calendar' ),
				$unit[0]
			)
			: sprintf(
				/* translators: 1: recurrence interval, 2: plural recurrence unit. */
				__( 'Every %1$d %2$s', 'mime-simple-events-calendar' ),
				$interval,
				$unit[1]
			);
		$end = $rule->end();

		if ( null !== $end->count() ) {
			return $summary . ' · ' . sprintf(
				/* translators: %d: total number of events in the schedule. */
				__( '%d events', 'mime-simple-events-calendar' ),
				$end->count()
			);
		}

		if ( null !== $end->until_date() ) {
			return $summary . ' · ' . sprintf(
				/* translators: %s: inclusive recurrence end date in ISO format. */
				__( 'through %s', 'mime-simple-events-calendar' ),
				$end->until_date()
			);
		}

		return $summary . ' · ' . __( 'no end date', 'mime-simple-events-calendar' );
	}
}
