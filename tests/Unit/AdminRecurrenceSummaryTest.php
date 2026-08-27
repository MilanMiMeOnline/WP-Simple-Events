<?php
/**
 * Tests for compact recurrence summaries in wp-admin.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\AdminRecurrenceSummary;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEnd;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Recurrence\SpecificDatesSchedule;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurrenceAggregateStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminRecurrenceSummary::class )]
/** Freezes safe, concise list-table recurrence summaries. */
final class AdminRecurrenceSummaryTest extends TestCase {
	/** One-off and corrupt states remain distinguishable and safe. */
	public function test_summarizes_absent_and_corrupt_state(): void {
		$store   = new FakeRecurrenceAggregateStore();
		$summary = new AdminRecurrenceSummary( $store );

		self::assertSame( 'One-off event', $summary->summarize( 41 ) );

		$store->corrupt = true;
		self::assertSame( 'Recurrence unavailable', $summary->summarize( 41 ) );
	}

	/** Generated rules expose frequency and their meaningful end condition. */
	public function test_summarizes_generated_rules(): void {
		$store   = new FakeRecurrenceAggregateStore();
		$summary = new AdminRecurrenceSummary( $store );

		$store->aggregate = $this->aggregate( RecurrenceRule::weekly( array( 1 ), 2, RecurrenceEnd::after( 10 ) ) );
		self::assertSame( 'Every 2 weeks · 10 events', $summary->summarize( 41 ) );

		$store->aggregate = $this->aggregate( RecurrenceRule::daily( 1, RecurrenceEnd::on( '2027-06-30' ) ) );
		self::assertSame( 'Every day · through 2027-06-30', $summary->summarize( 41 ) );

		$store->aggregate = $this->aggregate( RecurrenceRule::monthly_on_day( 4 ) );
		self::assertSame( 'Every month · no end date', $summary->summarize( 41 ) );
	}

	/** Explicit dates report their exact bounded total. */
	public function test_summarizes_selected_dates(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate(
			SpecificDatesSchedule::from_dates( array( '2027-01-04', '2027-02-08', '2027-03-15' ) )
		);

		self::assertSame(
			'Selected dates · 3 events',
			( new AdminRecurrenceSummary( $store ) )->summarize( 41 )
		);
	}

	/**
	 * Build one canonical aggregate with the supplied root definition.
	 *
	 * @param RecurrenceRule|SpecificDatesSchedule $definition Root schedule definition.
	 */
	private function aggregate( RecurrenceRule|SpecificDatesSchedule $definition ): RecurrenceAggregate {
		$range = EventDateRange::from_local(
			'2027-01-04T19:00:00',
			'2027-01-04T21:00:00',
			false,
			'Europe/Brussels'
		);

		return RecurrenceAggregate::create(
			'019c1d83-1798-4fac-a66d-ae8d67c46319',
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $range->start_local(), $range, $definition ) )
		);
	}
}
