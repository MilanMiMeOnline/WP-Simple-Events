<?php
/**
 * Tests for FullCalendar event date values.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Calendar\CalendarEventDates;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies floating wall times, explicit instants and all-day boundaries.
 */
#[CoversClass( CalendarEventDates::class )]
final class CalendarEventDatesTest extends TestCase {
	/** All-day output converts the stored inclusive end into an exclusive date. */
	public function test_all_day_end_is_exclusive_for_fullcalendar(): void {
		$dates = $this->format_range( '2026-07-20', '2026-07-22', true, 'Europe/Brussels' );

		self::assertSame( '2026-07-20', $dates['start'] ?? null );
		self::assertSame( '2026-07-23', $dates['end'] ?? null );
		self::assertSame( 'Europe/Brussels', $dates['timezone'] ?? null );
		self::assertNull( $dates['start_instant'] ?? null );
		self::assertNull( $dates['end_instant'] ?? null );
	}

	/** Timed output floats for UI placement while retaining machine instants. */
	public function test_timed_values_preserve_wall_time_and_machine_offset(): void {
		$dates = $this->format_range(
			'2026-07-19T12:05:00',
			'2026-07-19T22:05:00',
			false,
			'+00:00'
		);

		self::assertSame( '2026-07-19T12:05:00', $dates['start'] ?? null );
		self::assertSame( '2026-07-19T22:05:00', $dates['end'] ?? null );
		self::assertSame( '2026-07-19T12:05:00+00:00', $dates['start_instant'] ?? null );
		self::assertSame( '2026-07-19T22:05:00+00:00', $dates['end_instant'] ?? null );
	}

	/**
	 * Supported offset extremes retain the same saved calendar dates.
	 *
	 * @param string $timezone       Stored fixed-offset timezone.
	 * @param string $expected_offset Expected output offset.
	 */
	#[DataProvider( 'fixed_offset_ranges' )]
	public function test_fixed_offset_extremes_preserve_local_dates(
		string $timezone,
		string $expected_offset
	): void {
		$dates = $this->format_range(
			'2026-01-31T23:30:00',
			'2026-02-01T00:30:00',
			false,
			$timezone
		);

		self::assertSame( '2026-01-31T23:30:00', $dates['start'] ?? null );
		self::assertSame( '2026-02-01T00:30:00', $dates['end'] ?? null );
		self::assertStringEndsWith( $expected_offset, $dates['start_instant'] ?? '' );
	}

	/**
	 * Provide supported fixed-offset boundaries.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function fixed_offset_ranges(): array {
		return array(
			'maximum positive' => array( '+14:00', '+14:00' ),
			'maximum negative' => array( '-14:00', '-14:00' ),
			'fractional'       => array( '+05:30', '+05:30' ),
		);
	}

	/**
	 * Genuine overnight and multi-day boundaries are not collapsed.
	 *
	 * @param string $start    Canonical local start.
	 * @param string $end      Canonical local end.
	 * @param string $timezone Stored event timezone.
	 */
	#[DataProvider( 'spanning_ranges' )]
	public function test_genuine_spanning_events_keep_both_local_boundaries(
		string $start,
		string $end,
		string $timezone
	): void {
		$dates = $this->format_range( $start, $end, false, $timezone );

		self::assertSame( $start, $dates['start'] ?? null );
		self::assertSame( $end, $dates['end'] ?? null );
	}

	/**
	 * Provide genuine cross-day event boundaries.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function spanning_ranges(): array {
		return array(
			'overnight' => array( '2026-08-11T22:00:00', '2026-08-12T02:00:00', 'Europe/Brussels' ),
			'multi-day' => array( '2026-08-13T09:00:00', '2026-08-15T17:00:00', '+05:30' ),
		);
	}

	/**
	 * IANA DST transitions retain the offset applicable to each boundary.
	 *
	 * @param string $start          Canonical local start.
	 * @param string $end            Canonical local end.
	 * @param string $timezone       Stored IANA timezone.
	 * @param string $expected_start Expected machine start.
	 * @param string $expected_end   Expected machine end.
	 */
	#[DataProvider( 'dst_ranges' )]
	public function test_dst_transitions_keep_boundary_specific_offsets(
		string $start,
		string $end,
		string $timezone,
		string $expected_start,
		string $expected_end
	): void {
		$dates = $this->format_range( $start, $end, false, $timezone );

		self::assertSame( $expected_start, $dates['start_instant'] ?? null );
		self::assertSame( $expected_end, $dates['end_instant'] ?? null );
	}

	/**
	 * Provide representative IANA daylight-saving transitions.
	 *
	 * @return array<string, array{string, string, string, string, string}>
	 */
	public static function dst_ranges(): array {
		return array(
			'Europe spring'  => array(
				'2026-03-29T01:30:00',
				'2026-03-29T03:30:00',
				'Europe/Brussels',
				'2026-03-29T01:30:00+01:00',
				'2026-03-29T03:30:00+02:00',
			),
			'America spring' => array(
				'2026-03-08T01:30:00',
				'2026-03-08T03:30:00',
				'America/New_York',
				'2026-03-08T01:30:00-05:00',
				'2026-03-08T03:30:00-04:00',
			),
		);
	}

	/** Corrupt or inconsistent stored values never reach the public feed. */
	public function test_invalid_or_mismatched_values_return_no_dates(): void {
		$formatter = new CalendarEventDates();
		$range     = EventDateRange::from_local(
			'2026-07-20T09:30:00',
			'2026-07-20T11:00:00',
			false,
			'Europe/Brussels'
		);

		self::assertNull( $formatter->format( 'invalid', 'invalid', 1, 2, false, 'Europe/Brussels' ) );
		self::assertNull(
			$formatter->format(
				$range->start_local(),
				$range->end_local(),
				$range->start_utc(),
				$range->end_utc() + 1,
				false,
				$range->timezone()
			)
		);
	}

	/**
	 * Build and format one range through the production validator.
	 *
	 * @param string $start    Canonical local start.
	 * @param string $end      Canonical local end.
	 * @param bool   $all_day  Whether the range is all day.
	 * @param string $timezone Stored event timezone.
	 * @return array{start: string, end: string, timezone: string, start_instant: string|null, end_instant: string|null}|null
	 */
	private function format_range(
		string $start,
		string $end,
		bool $all_day,
		string $timezone
	): ?array {
		$range = EventDateRange::from_local( $start, $end, $all_day, $timezone );

		return ( new CalendarEventDates() )->format(
			$range->start_local(),
			$range->end_local(),
			$range->start_utc(),
			$range->end_utc(),
			$range->all_day(),
			$range->timezone()
		);
	}
}
