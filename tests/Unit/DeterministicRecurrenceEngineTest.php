<?php
/**
 * Tests for deterministic bounded recurrence expansion.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\DeterministicRecurrenceEngine;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEnd;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationFailure;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationResult;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceSlot;
use MiMe\WPSimpleEvents\Recurrence\SpecificDatesSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves calendar semantics, DST safety and all expansion bounds.
 */
#[CoversClass( DeterministicRecurrenceEngine::class )]
#[CoversClass( RecurrenceGenerationException::class )]
#[CoversClass( RecurrenceGenerationResult::class )]
#[CoversClass( RecurrenceSlot::class )]
final class DeterministicRecurrenceEngineTest extends TestCase {
	/**
	 * Stateless recurrence engine under test.
	 *
	 * @var DeterministicRecurrenceEngine
	 */
	private DeterministicRecurrenceEngine $engine;

	/**
	 * Create a fresh stateless engine.
	 */
	protected function setUp(): void {
		$this->engine = new DeterministicRecurrenceEngine();
	}

	/**
	 * Daily local wall time survives the spring DST offset change.
	 */
	public function test_daily_rule_preserves_wall_time_across_dst(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-03-27T09:00', '2026-03-27T11:00', false, 'Europe/Brussels' ),
			RecurrenceRule::daily(),
			RecurrenceGenerationWindow::between( '2026-03-27', '2026-03-31' )
		);

		self::assertSame(
			array(
				'2026-03-27T09:00:00',
				'2026-03-28T09:00:00',
				'2026-03-29T09:00:00',
				'2026-03-30T09:00:00',
				'2026-03-31T09:00:00',
			),
			$this->starts( $result )
		);
		self::assertSame( 86_400, $result->slots()[1]->date_range()->start_utc() - $result->slots()[0]->date_range()->start_utc() );
		self::assertSame( 82_800, $result->slots()[2]->date_range()->start_utc() - $result->slots()[1]->date_range()->start_utc() );
		self::assertSame( 86_400, $result->slots()[3]->date_range()->start_utc() - $result->slots()[2]->date_range()->start_utc() );
	}

	/**
	 * A rule that reaches a nonexistent spring-forward time fails closed.
	 */
	public function test_nonexistent_dst_time_is_rejected(): void {
		$this->expect_generation_failure( RecurrenceGenerationFailure::INVALID_LOCAL_TIME );

		$this->engine->generate(
			EventDateRange::from_local( '2026-03-28T02:30', '2026-03-28T03:30', false, 'Europe/Brussels' ),
			RecurrenceRule::daily(),
			RecurrenceGenerationWindow::between( '2026-03-28', '2026-03-30' )
		);
	}

	/**
	 * A rule that reaches an ambiguous fall-back time also fails closed.
	 */
	public function test_ambiguous_dst_time_is_rejected(): void {
		$this->expect_generation_failure( RecurrenceGenerationFailure::INVALID_LOCAL_TIME );

		$this->engine->generate(
			EventDateRange::from_local( '2026-10-24T02:30', '2026-10-24T03:30', false, 'Europe/Brussels' ),
			RecurrenceRule::daily(),
			RecurrenceGenerationWindow::between( '2026-10-24', '2026-10-26' )
		);
	}

	/**
	 * Weekly rules follow ISO weekdays and count scheduled slots from the seed.
	 */
	public function test_weekly_rule_uses_selected_weekdays_and_count(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-09-02', null, true, 'UTC' ),
			RecurrenceRule::weekly( array( 2, 4 ), 1, RecurrenceEnd::after( 3 ) ),
			RecurrenceGenerationWindow::between( '2026-09-01', '2026-10-01' )
		);

		self::assertSame(
			array( '2026-09-03', '2026-09-08', '2026-09-10' ),
			$this->starts( $result )
		);
		self::assertSame( '2026-09-03', $result->slots()[0]->recurrence_id() );
	}

	/**
	 * Weekly intervals are anchored to the ISO week containing the seed.
	 */
	public function test_multiweek_interval_remains_anchored(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-08-31', null, true, 'UTC' ),
			RecurrenceRule::weekly( array( 1 ), 2 ),
			RecurrenceGenerationWindow::between( '2026-08-31', '2026-09-30' )
		);

		self::assertSame( array( '2026-08-31', '2026-09-14', '2026-09-28' ), $this->starts( $result ) );
	}

	/**
	 * Inclusive end dates include an occurrence starting on that day.
	 */
	public function test_inclusive_end_date(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-09-01', null, true, 'UTC' ),
			RecurrenceRule::daily( 2, RecurrenceEnd::on( '2026-09-05' ) ),
			RecurrenceGenerationWindow::between( '2026-09-01', '2026-09-30' )
		);

		self::assertSame( array( '2026-09-01', '2026-09-03', '2026-09-05' ), $this->starts( $result ) );
	}

	/**
	 * Invalid calendar days are skipped instead of moved to a month end.
	 */
	public function test_month_day_rule_skips_short_months(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-01-31', null, true, 'UTC' ),
			RecurrenceRule::monthly_on_day( 31 ),
			RecurrenceGenerationWindow::between( '2026-01-01', '2026-06-30' )
		);

		self::assertSame( array( '2026-01-31', '2026-03-31', '2026-05-31' ), $this->starts( $result ) );
	}

	/**
	 * Monthly intervals stay anchored to the seed month.
	 */
	public function test_multimonth_interval_remains_anchored(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-01-15', null, true, 'UTC' ),
			RecurrenceRule::monthly_on_day( 15, 2 ),
			RecurrenceGenerationWindow::between( '2026-01-01', '2026-06-30' )
		);

		self::assertSame( array( '2026-01-15', '2026-03-15', '2026-05-15' ), $this->starts( $result ) );
	}

	/**
	 * A fifth weekday is skipped in months where it does not exist.
	 */
	public function test_fifth_weekday_rule_skips_missing_occurrences(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-01-01', null, true, 'UTC' ),
			RecurrenceRule::monthly_on_ordinal_weekday( 5, 1 ),
			RecurrenceGenerationWindow::between( '2026-01-01', '2026-06-30' )
		);

		self::assertSame( array( '2026-03-30', '2026-06-29' ), $this->starts( $result ) );
	}

	/**
	 * Last-weekday rules remain deterministic across month lengths.
	 */
	public function test_last_weekday_rule(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-01-01', null, true, 'UTC' ),
			RecurrenceRule::monthly_on_ordinal_weekday( -1, 5 ),
			RecurrenceGenerationWindow::between( '2026-01-01', '2026-03-31' )
		);

		self::assertSame( array( '2026-01-30', '2026-02-27', '2026-03-27' ), $this->starts( $result ) );
	}

	/**
	 * Leap-day rules skip non-leap years without consuming the occurrence count.
	 */
	public function test_yearly_leap_day_skips_invalid_years(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2024-02-29', null, true, 'UTC' ),
			RecurrenceRule::yearly_on( 2, 29, 1, RecurrenceEnd::after( 2 ) ),
			RecurrenceGenerationWindow::between( '2027-10-01', '2028-03-01' )
		);

		self::assertSame( array( '2028-02-29' ), $this->starts( $result ) );
	}

	/**
	 * Specific dates are sorted and reuse the template's local range shape.
	 */
	public function test_specific_dates_preserve_cross_midnight_shape(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-09-01T22:00', '2026-09-02T01:30', false, 'Europe/Brussels' ),
			SpecificDatesSchedule::from_dates( array( '2026-10-03', '2026-09-01', '2026-09-20' ) ),
			RecurrenceGenerationWindow::between( '2026-09-01', '2026-10-31' )
		);

		self::assertSame(
			array( '2026-09-01T22:00:00', '2026-09-20T22:00:00', '2026-10-03T22:00:00' ),
			$this->starts( $result )
		);
		self::assertSame( '2026-10-04T01:30:00', $result->slots()[2]->date_range()->end_local() );
	}

	/**
	 * Multi-day events starting before the window are included when they overlap it.
	 */
	public function test_generation_includes_ranges_overlapping_window_start(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-01-01', '2026-01-03', true, 'UTC' ),
			RecurrenceRule::daily(),
			RecurrenceGenerationWindow::between( '2026-01-03', '2026-01-03' )
		);

		self::assertSame( array( '2026-01-01', '2026-01-02', '2026-01-03' ), $this->starts( $result ) );
	}

	/**
	 * A count exhausted before the requested window produces a complete empty result.
	 */
	public function test_count_exhausted_before_window_returns_empty_result(): void {
		$result = $this->engine->generate(
			EventDateRange::from_local( '2026-01-01', null, true, 'UTC' ),
			RecurrenceRule::daily( 1, RecurrenceEnd::after( 3 ) ),
			RecurrenceGenerationWindow::between( '2026-02-01', '2026-02-28' )
		);

		self::assertSame( array(), $result->slots() );
		self::assertSame( '2026-02-01', $result->coverage_from() );
		self::assertSame( '2026-02-28', $result->coverage_through() );
	}

	/**
	 * Output is never silently truncated when the row cap is reached.
	 */
	public function test_row_limit_fails_instead_of_truncating(): void {
		$this->expect_generation_failure( RecurrenceGenerationFailure::ROW_LIMIT_EXCEEDED );

		$this->engine->generate(
			EventDateRange::from_local( '2026-01-01', null, true, 'UTC' ),
			RecurrenceRule::daily(),
			RecurrenceGenerationWindow::between( '2026-01-01', '2026-01-10', 3 )
		);
	}

	/**
	 * Extremely old never-ending series cannot force unbounded internal scanning.
	 */
	public function test_internal_evaluation_limit_fails_closed(): void {
		$this->expect_generation_failure( RecurrenceGenerationFailure::EVALUATION_LIMIT_REACHED );

		$this->engine->generate(
			EventDateRange::from_local( '0001-01-01', null, true, 'UTC' ),
			RecurrenceRule::daily(),
			RecurrenceGenerationWindow::between( '2026-01-01', '2026-01-10' )
		);
	}

	/**
	 * Definitions cannot introduce dates before their series template.
	 */
	public function test_specific_date_before_series_start_is_rejected(): void {
		$this->expect_generation_failure( RecurrenceGenerationFailure::DATE_OUTSIDE_SUPPORTED_RANGE );

		$this->engine->generate(
			EventDateRange::from_local( '2026-09-10', null, true, 'UTC' ),
			SpecificDatesSchedule::from_dates( array( '2026-09-09', '2026-09-12' ) ),
			RecurrenceGenerationWindow::between( '2026-09-01', '2026-09-30' )
		);
	}

	/**
	 * A generated rule cannot terminate before its series start.
	 */
	public function test_end_before_series_start_is_rejected(): void {
		$this->expect_generation_failure( RecurrenceGenerationFailure::DATE_OUTSIDE_SUPPORTED_RANGE );

		$this->engine->generate(
			EventDateRange::from_local( '2026-09-10', null, true, 'UTC' ),
			RecurrenceRule::daily( 1, RecurrenceEnd::on( '2026-09-09' ) ),
			RecurrenceGenerationWindow::between( '2026-09-01', '2026-09-30' )
		);
	}

	/**
	 * Register an expected stable generation failure.
	 *
	 * @param RecurrenceGenerationFailure $reason Expected stable failure reason.
	 */
	private function expect_generation_failure( RecurrenceGenerationFailure $reason ): void {
		$this->expectException( RecurrenceGenerationException::class );
		$this->expectExceptionObject( $this->failure( $reason ) );
	}

	/**
	 * Build an exception instance with the same stable reason and message.
	 *
	 * @param RecurrenceGenerationFailure $reason Expected stable failure reason.
	 */
	private function failure( RecurrenceGenerationFailure $reason ): RecurrenceGenerationException {
		return match ( $reason ) {
			RecurrenceGenerationFailure::INVALID_LOCAL_TIME => RecurrenceGenerationException::invalid_local_time(),
			RecurrenceGenerationFailure::ROW_LIMIT_EXCEEDED => RecurrenceGenerationException::row_limit_exceeded(),
			RecurrenceGenerationFailure::EVALUATION_LIMIT_REACHED => RecurrenceGenerationException::evaluation_limit_reached(),
			RecurrenceGenerationFailure::DATE_OUTSIDE_SUPPORTED_RANGE => RecurrenceGenerationException::date_outside_supported_range(),
		};
	}

	/**
	 * Return generated canonical local starts.
	 *
	 * @param RecurrenceGenerationResult $result Complete bounded result.
	 * @return string[]
	 */
	private function starts( RecurrenceGenerationResult $result ): array {
		return array_map(
			static fn ( RecurrenceSlot $slot ): string => $slot->date_range()->start_local(),
			$result->slots()
		);
	}
}
