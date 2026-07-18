<?php
/**
 * Tests for FullCalendar event date values.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use MiMe\WPSimpleEvents\Calendar\CalendarEventDates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies timed offsets and exclusive all-day end dates.
 */
#[CoversClass( CalendarEventDates::class )]
final class CalendarEventDatesTest extends TestCase {
	/**
	 * All-day output converts the stored inclusive end into an exclusive date.
	 */
	public function test_all_day_end_is_exclusive_for_fullcalendar(): void {
		$dates = ( new CalendarEventDates() )->format(
			$this->timestamp( '2026-07-20 00:00:00', 'Europe/Brussels' ),
			$this->timestamp( '2026-07-22 23:59:59', 'Europe/Brussels' ),
			true,
			'Europe/Brussels'
		);

		self::assertSame(
			array(
				'start' => '2026-07-20',
				'end'   => '2026-07-23',
			),
			$dates
		);
	}

	/**
	 * Timed output retains the event's captured UTC offset.
	 */
	public function test_timed_values_retain_the_event_offset(): void {
		$dates = ( new CalendarEventDates() )->format(
			$this->timestamp( '2026-07-20 09:30:00', 'Europe/Brussels' ),
			$this->timestamp( '2026-07-20 11:00:00', 'Europe/Brussels' ),
			false,
			'Europe/Brussels'
		);

		self::assertSame( '2026-07-20T09:30:00+02:00', $dates['start'] ?? null );
		self::assertSame( '2026-07-20T11:00:00+02:00', $dates['end'] ?? null );
	}

	/**
	 * Corrupt stored values never reach the public feed.
	 */
	public function test_invalid_values_return_no_dates(): void {
		$formatter = new CalendarEventDates();

		self::assertNull( $formatter->format( 0, 1, false, 'Europe/Brussels' ) );
		self::assertNull( $formatter->format( 2, 1, false, 'Europe/Brussels' ) );
		self::assertNull( $formatter->format( 1, 2, false, '../../etc/passwd' ) );
	}

	/**
	 * Create a deterministic timestamp for a local date-time.
	 *
	 * @param string $local    Local date-time.
	 * @param string $timezone IANA timezone.
	 */
	private function timestamp( string $local, string $timezone ): int {
		return ( new DateTimeImmutable( $local, new DateTimeZone( $timezone ) ) )->getTimestamp();
	}
}
