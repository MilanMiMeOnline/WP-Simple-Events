<?php
/**
 * Tests for canonical event date ranges.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies local date normalization and UTC conversion boundaries.
 */
#[CoversClass( EventDateRange::class )]
final class EventDateRangeTest extends TestCase {
	/**
	 * Winter and summer dates use their correct Brussels UTC offset.
	 */
	public function test_timed_ranges_respect_daylight_saving_offsets(): void {
		$winter = EventDateRange::from_local(
			'2026-01-15T10:00',
			'2026-01-15T11:30',
			false,
			'Europe/Brussels'
		);
		$summer = EventDateRange::from_local(
			'2026-07-15T10:00',
			'2026-07-15T11:30',
			false,
			'Europe/Brussels'
		);

		self::assertSame( '2026-01-15T09:00:00+00:00', gmdate( 'c', $winter->start_utc() ) );
		self::assertSame( '2026-07-15T08:00:00+00:00', gmdate( 'c', $summer->start_utc() ) );
		self::assertSame( '2026-01-15T10:00:00', $winter->start_local() );
	}

	/**
	 * A missing timed end is normalized to the start moment.
	 */
	public function test_missing_timed_end_uses_start(): void {
		$range = EventDateRange::from_local( '2026-04-10T18:45', null, false, 'UTC' );

		self::assertSame( $range->start_local(), $range->end_local() );
		self::assertSame( $range->start_utc(), $range->end_utc() );
	}

	/**
	 * Inclusive all-day dates span the complete local end date across DST.
	 */
	public function test_all_day_range_is_inclusive_across_dst_transition(): void {
		$range = EventDateRange::from_local( '2026-03-29', null, true, 'Europe/Brussels' );

		self::assertTrue( $range->all_day() );
		self::assertSame( '2026-03-28T23:00:00+00:00', gmdate( 'c', $range->start_utc() ) );
		self::assertSame( '2026-03-29T21:59:59+00:00', gmdate( 'c', $range->end_utc() ) );
		self::assertSame( 82_799, $range->end_utc() - $range->start_utc() );
	}

	/**
	 * A fixed offset produced by WordPress is supported deterministically.
	 */
	public function test_wordpress_fixed_offset_is_supported(): void {
		$range = EventDateRange::from_local( '2026-06-01T10:00', null, false, '+02:00' );

		self::assertSame( '+02:00', $range->timezone() );
		self::assertSame( '2026-06-01T08:00:00+00:00', gmdate( 'c', $range->start_utc() ) );
	}

	/**
	 * Reversed ranges are rejected.
	 */
	public function test_end_before_start_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		EventDateRange::from_local(
			'2026-05-02T10:00',
			'2026-05-02T09:59',
			false,
			'Europe/Brussels'
		);
	}

	/**
	 * A nonexistent local clock time is not silently shifted by PHP.
	 */
	public function test_nonexistent_spring_clock_time_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		EventDateRange::from_local( '2026-03-29T02:30', null, false, 'Europe/Brussels' );
	}

	/**
	 * A repeated autumn clock time is rejected because the input has no offset.
	 */
	public function test_ambiguous_autumn_clock_time_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		EventDateRange::from_local( '2026-10-25T02:30', null, false, 'Europe/Brussels' );
	}

	/**
	 * Invalid calendar dates are rejected.
	 */
	public function test_invalid_calendar_date_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		EventDateRange::from_local( '2026-02-30', null, true, 'Europe/Brussels' );
	}

	/**
	 * Arbitrary timezone input is rejected.
	 */
	public function test_invalid_timezone_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		EventDateRange::from_local( '2026-06-01T10:00', null, false, 'Not/A_Timezone' );
	}
}
