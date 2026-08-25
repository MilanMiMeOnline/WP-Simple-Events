<?php
/**
 * Deterministic bounded recurrence expansion.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;

/**
 * Expands plugin-owned local-calendar rules without silently shifting wall time.
 */
final class DeterministicRecurrenceEngine implements RecurrenceEngine {
	private const MAX_EVALUATED_PERIODS = 100_000;
	private const DATE_FORMAT           = 'Y-m-d';

	/**
	 * Expand one definition into complete chronological recurrence slots.
	 *
	 * @param EventDateRange             $template   Validated template occurrence range.
	 * @param RecurrenceDefinition       $definition Validated recurrence definition.
	 * @param RecurrenceGenerationWindow $window     Explicit bounded output window.
	 * @throws RecurrenceGenerationException When expansion is unsafe or exceeds a bound.
	 */
	public function generate(
		EventDateRange $template,
		RecurrenceDefinition $definition,
		RecurrenceGenerationWindow $window
	): RecurrenceGenerationResult {
		$seed_date = substr( $template->start_local(), 0, 10 );
		$end_date  = substr( $template->end_local(), 0, 10 );
		$day_span  = $this->date( $seed_date )->diff( $this->date( $end_date ) );
		$day_span  = (int) $day_span->format( '%r%a' );

		if ( $day_span < 0 ) {
			throw RecurrenceGenerationException::date_outside_supported_range();
		}

		if ( $definition instanceof RecurrenceRule ) {
			$candidates = $this->rule_dates( $seed_date, $definition, $window->through_date() );
		} elseif ( $definition instanceof SpecificDatesSchedule ) {
			$candidates = $this->specific_dates( $seed_date, $definition, $window->through_date() );
		} else {
			throw RecurrenceGenerationException::date_outside_supported_range();
		}

		$slots = array();

		foreach ( $candidates as $candidate_date ) {
			$candidate_end_date = $this->date( $candidate_date )
				->modify( '+' . $day_span . ' days' )
				->format( self::DATE_FORMAT );

			if ( $candidate_end_date < $window->from_date() ) {
				continue;
			}

			if ( count( $slots ) >= $window->max_rows() ) {
				throw RecurrenceGenerationException::row_limit_exceeded();
			}

			$range   = $this->range_for_date( $template, $candidate_date, $candidate_end_date );
			$slots[] = new RecurrenceSlot( $range->start_local(), $range );
		}

		return new RecurrenceGenerationResult(
			$slots,
			$window->from_date(),
			$window->through_date()
		);
	}

	/**
	 * Yield candidate local dates for a generated rule.
	 *
	 * @param string         $seed_date  Canonical series start date.
	 * @param RecurrenceRule $rule       Validated generated rule.
	 * @param string         $window_end Inclusive generation boundary.
	 * @return Generator<int, string>
	 * @throws RecurrenceGenerationException When the end precedes the series start.
	 */
	private function rule_dates( string $seed_date, RecurrenceRule $rule, string $window_end ): Generator {
		$until = $rule->end()->until_date();

		if ( null !== $until && $until < $seed_date ) {
			throw RecurrenceGenerationException::date_outside_supported_range();
		}

		$boundary    = null !== $until && $until < $window_end ? $until : $window_end;
		$evaluated   = 0;
		$yielded     = 0;
		$count_limit = $rule->end()->count();

		$generator = match ( $rule->frequency() ) {
			RecurrenceFrequency::DAILY   => $this->daily_dates( $seed_date, $rule->interval(), $boundary, $evaluated ),
			RecurrenceFrequency::WEEKLY  => $this->weekly_dates( $seed_date, $rule->interval(), $rule->weekdays(), $boundary, $evaluated ),
			RecurrenceFrequency::MONTHLY => $this->monthly_dates( $seed_date, $rule, $boundary, $evaluated ),
			RecurrenceFrequency::YEARLY  => $this->yearly_dates( $seed_date, $rule, $boundary, $evaluated ),
		};

		foreach ( $generator as $candidate ) {
			++$yielded;

			if ( null !== $count_limit && $yielded > $count_limit ) {
				return;
			}

			yield $candidate;

			if ( null !== $count_limit && $yielded === $count_limit ) {
				return;
			}
		}
	}

	/**
	 * Yield fixed-interval daily dates.
	 *
	 * @param string $seed_date Canonical series start date.
	 * @param int    $interval  Positive day interval.
	 * @param string $boundary  Inclusive final date.
	 * @param int    $evaluated Shared internal evaluation counter.
	 * @return Generator<int, string>
	 */
	private function daily_dates( string $seed_date, int $interval, string $boundary, int &$evaluated ): Generator {
		$seed = $this->date( $seed_date );

		for ( $index = 0; ; ++$index ) {
			$this->guard_evaluations( $evaluated );
			$candidate = $seed->modify( '+' . ( $index * $interval ) . ' days' )->format( self::DATE_FORMAT );

			if ( $candidate > $boundary ) {
				return;
			}

			yield $candidate;
		}
	}

	/**
	 * Yield fixed-interval weekly dates.
	 *
	 * @param string $seed_date Canonical series start date.
	 * @param int    $interval  Positive week interval.
	 * @param int[]  $weekdays  Selected ISO weekdays.
	 * @param string $boundary  Inclusive final date.
	 * @param int    $evaluated Shared internal evaluation counter.
	 * @return Generator<int, string>
	 */
	private function weekly_dates(
		string $seed_date,
		int $interval,
		array $weekdays,
		string $boundary,
		int &$evaluated
	): Generator {
		$seed       = $this->date( $seed_date );
		$week_start = $seed->modify( '-' . ( (int) $seed->format( 'N' ) - 1 ) . ' days' );

		for ( $week_index = 0; ; ++$week_index ) {
			$current_week = $week_start->modify( '+' . ( $week_index * $interval ) . ' weeks' );

			foreach ( $weekdays as $weekday ) {
				$this->guard_evaluations( $evaluated );
				$candidate = $current_week->modify( '+' . ( $weekday - 1 ) . ' days' )->format( self::DATE_FORMAT );

				if ( $candidate < $seed_date ) {
					continue;
				}

				if ( $candidate > $boundary ) {
					return;
				}

				yield $candidate;
			}
		}
	}

	/**
	 * Yield monthly calendar or ordinal-weekday dates.
	 *
	 * @param string         $seed_date Canonical series start date.
	 * @param RecurrenceRule $rule      Validated monthly rule.
	 * @param string         $boundary  Inclusive final date.
	 * @param int            $evaluated Shared internal evaluation counter.
	 * @return Generator<int, string>
	 */
	private function monthly_dates( string $seed_date, RecurrenceRule $rule, string $boundary, int &$evaluated ): Generator {
		$seed       = $this->date( $seed_date );
		$seed_month = ( (int) $seed->format( 'Y' ) * 12 ) + (int) $seed->format( 'n' ) - 1;

		for ( $index = 0; ; ++$index ) {
			$this->guard_evaluations( $evaluated );
			$month_index = $seed_month + ( $index * $rule->interval() );
			$year        = intdiv( $month_index, 12 );
			$month       = ( $month_index % 12 ) + 1;
			$first       = sprintf( '%04d-%02d-01', $year, $month );

			if ( $first > $boundary ) {
				return;
			}

			$candidate = MonthlyRecurrenceMode::DAY_OF_MONTH === $rule->monthly_mode()
				? $this->calendar_day( $year, $month, (int) $rule->month_day() )
				: $this->ordinal_weekday( $year, $month, (int) $rule->ordinal(), (int) $rule->weekday() );

			if ( null === $candidate || $candidate < $seed_date ) {
				continue;
			}

			if ( $candidate > $boundary ) {
				return;
			}

			yield $candidate;
		}
	}

	/**
	 * Yield yearly same-month-and-day dates.
	 *
	 * @param string         $seed_date Canonical series start date.
	 * @param RecurrenceRule $rule      Validated yearly rule.
	 * @param string         $boundary  Inclusive final date.
	 * @param int            $evaluated Shared internal evaluation counter.
	 * @return Generator<int, string>
	 */
	private function yearly_dates( string $seed_date, RecurrenceRule $rule, string $boundary, int &$evaluated ): Generator {
		$seed_year = (int) substr( $seed_date, 0, 4 );

		for ( $index = 0; ; ++$index ) {
			$this->guard_evaluations( $evaluated );
			$year       = $seed_year + ( $index * $rule->interval() );
			$year_start = sprintf( '%04d-01-01', $year );

			if ( $year_start > $boundary ) {
				return;
			}

			$candidate = $this->calendar_day( $year, (int) $rule->month(), (int) $rule->month_day() );

			if ( null === $candidate || $candidate < $seed_date ) {
				continue;
			}

			if ( $candidate > $boundary ) {
				return;
			}

			yield $candidate;
		}
	}

	/**
	 * Yield explicit dates inside the requested boundary.
	 *
	 * @param string                $seed_date Canonical series start date.
	 * @param SpecificDatesSchedule $schedule  Validated explicit schedule.
	 * @param string                $boundary  Inclusive final date.
	 * @return Generator<int, string>
	 * @throws RecurrenceGenerationException When a date precedes the series start.
	 */
	private function specific_dates(
		string $seed_date,
		SpecificDatesSchedule $schedule,
		string $boundary
	): Generator {
		foreach ( $schedule->dates() as $date ) {
			if ( $date < $seed_date ) {
				throw RecurrenceGenerationException::date_outside_supported_range();
			}

			if ( $date > $boundary ) {
				return;
			}

			yield $date;
		}
	}

	/**
	 * Rebuild one range with the template's local clock fields and calendar span.
	 *
	 * @param EventDateRange $template   Validated template occurrence range.
	 * @param string         $start_date Generated canonical local start date.
	 * @param string         $end_date   Generated canonical local end date.
	 * @throws RecurrenceGenerationException When the generated local time is invalid.
	 */
	private function range_for_date(
		EventDateRange $template,
		string $start_date,
		string $end_date
	): EventDateRange {
		$start_suffix = $template->all_day() ? '' : substr( $template->start_local(), 10 );
		$end_suffix   = $template->all_day() ? '' : substr( $template->end_local(), 10 );

		try {
			return EventDateRange::from_local(
				$start_date . $start_suffix,
				$end_date . $end_suffix,
				$template->all_day(),
				$template->timezone()
			);
		} catch ( InvalidArgumentException ) {
			throw RecurrenceGenerationException::invalid_local_time();
		}
	}

	/**
	 * Return a valid calendar date or null for a deliberately skipped date.
	 *
	 * @param int $year  Calendar year.
	 * @param int $month Calendar month.
	 * @param int $day   Calendar day.
	 */
	private function calendar_day( int $year, int $month, int $day ): ?string {
		return checkdate( $month, $day, $year ) ? sprintf( '%04d-%02d-%02d', $year, $month, $day ) : null;
	}

	/**
	 * Return an ordinal weekday in one month, or null when fifth does not exist.
	 *
	 * @param int $year    Calendar year.
	 * @param int $month   Calendar month.
	 * @param int $ordinal Weekday ordinal or negative one for last.
	 * @param int $weekday ISO weekday.
	 */
	private function ordinal_weekday( int $year, int $month, int $ordinal, int $weekday ): ?string {
		if ( -1 === $ordinal ) {
			$last          = $this->date( sprintf( '%04d-%02d-01', $year, $month ) )->modify( 'last day of this month' );
			$days_backward = ( (int) $last->format( 'N' ) - $weekday + 7 ) % 7;

			return $last->modify( '-' . $days_backward . ' days' )->format( self::DATE_FORMAT );
		}

		$first        = $this->date( sprintf( '%04d-%02d-01', $year, $month ) );
		$days_forward = ( $weekday - (int) $first->format( 'N' ) + 7 ) % 7;
		$candidate    = $first->modify( '+' . ( $days_forward + ( ( $ordinal - 1 ) * 7 ) ) . ' days' );

		return (int) $candidate->format( 'n' ) === $month ? $candidate->format( self::DATE_FORMAT ) : null;
	}

	/**
	 * Parse an already canonical local calendar date for safe arithmetic.
	 *
	 * @param string $value Canonical local date.
	 * @throws RecurrenceGenerationException When arithmetic leaves the supported range.
	 */
	private function date( string $value ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );

		if ( false === $date || $date->format( self::DATE_FORMAT ) !== $value ) {
			throw RecurrenceGenerationException::date_outside_supported_range();
		}

		return $date;
	}

	/**
	 * Bound internal scanning even when an old series starts before the window.
	 *
	 * @param int $evaluated Shared internal evaluation counter.
	 * @throws RecurrenceGenerationException When the internal cap is exceeded.
	 */
	private function guard_evaluations( int &$evaluated ): void {
		++$evaluated;

		if ( $evaluated > self::MAX_EVALUATED_PERIODS ) {
			throw RecurrenceGenerationException::evaluation_limit_reached();
		}
	}
}
