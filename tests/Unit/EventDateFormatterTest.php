<?php
/**
 * Tests for public event date formatting.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use MiMe\WPSimpleEvents\Frontend\EventDateFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies local labels and machine-readable values for public cards.
 */
#[CoversClass( EventDateFormatter::class )]
final class EventDateFormatterTest extends TestCase {
	/**
	 * A same-day timed event uses one date and a time range.
	 */
	public function test_same_day_timed_event_uses_local_time_range(): void {
		$presentation = ( new EventDateFormatter() )->format(
			$this->timestamp( '2026-07-20 09:30:00', 'Europe/Brussels' ),
			$this->timestamp( '2026-07-20 11:00:00', 'Europe/Brussels' ),
			false,
			'Europe/Brussels'
		);

		self::assertNotNull( $presentation );
		self::assertSame( '2026-07-20, 09:30 – 11:00', $presentation->label );
		self::assertSame( '2026-07-20T09:30:00+02:00', $presentation->start_iso );
		self::assertSame( '2026-07-20T11:00:00+02:00', $presentation->end_iso );
	}

	/**
	 * A timed event with equal boundaries does not repeat its time.
	 */
	public function test_single_timed_moment_has_no_duplicate_end(): void {
		$timestamp    = $this->timestamp( '2026-07-20 09:30:00', 'Europe/Brussels' );
		$presentation = ( new EventDateFormatter() )->format( $timestamp, $timestamp, false, 'Europe/Brussels' );

		self::assertSame( '2026-07-20, 09:30', $presentation?->label );
	}

	/**
	 * A multi-day all-day event uses inclusive local dates without times.
	 */
	public function test_multi_day_all_day_event_uses_inclusive_dates(): void {
		$presentation = ( new EventDateFormatter() )->format(
			$this->timestamp( '2026-07-20 00:00:00', 'Europe/Brussels' ),
			$this->timestamp( '2026-07-22 23:59:59', 'Europe/Brussels' ),
			true,
			'Europe/Brussels'
		);

		self::assertNotNull( $presentation );
		self::assertSame( '2026-07-20 – 2026-07-22', $presentation->label );
		self::assertSame( '2026-07-20', $presentation->start_iso );
		self::assertSame( '2026-07-22', $presentation->end_iso );
	}

	/**
	 * A cross-day timed event labels both local dates.
	 */
	public function test_cross_day_timed_event_labels_both_dates(): void {
		$presentation = ( new EventDateFormatter() )->format(
			$this->timestamp( '2026-07-20 23:00:00', 'Europe/Brussels' ),
			$this->timestamp( '2026-07-21 01:00:00', 'Europe/Brussels' ),
			false,
			'Europe/Brussels'
		);

		self::assertSame( '2026-07-20, 23:00 – 2026-07-21, 01:00', $presentation?->label );
	}

	/**
	 * Corrupt boundaries or timezones never reach public markup.
	 */
	public function test_invalid_stored_values_return_no_presentation(): void {
		$formatter = new EventDateFormatter();

		self::assertNull( $formatter->format( 0, 1, false, 'Europe/Brussels' ) );
		self::assertNull( $formatter->format( 2, 1, false, 'Europe/Brussels' ) );
		self::assertNull( $formatter->format( 1, 2, false, '../../etc/passwd' ) );
	}

	/**
	 * Create a deterministic timestamp for a local event value.
	 *
	 * @param string $local    Local date-time.
	 * @param string $timezone IANA timezone.
	 */
	private function timestamp( string $local, string $timezone ): int {
		return ( new DateTimeImmutable( $local, new DateTimeZone( $timezone ) ) )->getTimestamp();
	}
}
