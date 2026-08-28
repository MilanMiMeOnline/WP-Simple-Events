<?php
/**
 * Tests for RFC 5545 calendar generation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\CalendarExport\IcsCalendarBuilder;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Verifies deterministic RFC 5545 output for supported event ranges. */
#[CoversClass( IcsCalendarBuilder::class )]
final class IcsCalendarBuilderTest extends TestCase {
	private const UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/** Timed output uses UTC, stable identity and escaped public text. */
	public function test_builds_one_timed_calendar_snapshot(): void {
		$output = ( new IcsCalendarBuilder() )->build(
			$this->snapshot(
				title: 'Jazz, blues; and more',
				description: "First line\r\nSecond \\ line",
				location: 'Town Hall, Room 2'
			)
		);

		self::assertStringStartsWith( "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $output );
		self::assertStringEndsWith( "END:VCALENDAR\r\n", $output );
		self::assertStringContainsString( "UID:wpse-75a59b8054f0d00a88663aab81f45ecb@mime-simple-events-calendar\r\n", $output );
		self::assertStringContainsString( "DTSTART:20260716T173000Z\r\n", $output );
		self::assertStringContainsString( "DTEND:20260716T193000Z\r\n", $output );
		self::assertStringContainsString( "SUMMARY:Jazz\\, blues\\; and more\r\n", $output );
		self::assertStringContainsString( 'DESCRIPTION:First line\\nSecond \\\\ line', $output );
		self::assertStringContainsString( 'LOCATION:Town Hall\\, Room 2', $output );
		self::assertStringContainsString( "STATUS:CONFIRMED\r\nTRANSP:OPAQUE\r\n", $output );
		self::assertStringNotContainsString( "\n", str_replace( "\r\n", '', $output ) );
	}

	/** Inclusive all-day ends become exclusive and postponed remains tentative. */
	public function test_builds_truthful_multi_day_all_day_output(): void {
		$range  = EventDateRange::from_local( '2026-10-24', '2026-10-25', true, 'Europe/Brussels' );
		$output = ( new IcsCalendarBuilder() )->build(
			$this->snapshot( range: $range, status: EventStatus::POSTPONED )
		);

		self::assertStringContainsString( "DTSTART;VALUE=DATE:20261024\r\n", $output );
		self::assertStringContainsString( "DTEND;VALUE=DATE:20261026\r\n", $output );
		self::assertStringContainsString( "STATUS:TENTATIVE\r\n", $output );
	}

	/** A start-only event never receives an invented end. */
	public function test_omits_equal_timed_end(): void {
		$range  = EventDateRange::from_local( '2026-07-16T19:30:00', null, false, '+02:00' );
		$output = ( new IcsCalendarBuilder() )->build( $this->snapshot( range: $range ) );

		self::assertStringContainsString( "DTSTART:20260716T173000Z\r\n", $output );
		self::assertStringNotContainsString( 'DTEND:', $output );
	}

	/** Long Unicode content folds without exceeding 75 octets or breaking UTF-8. */
	public function test_folds_unicode_content_on_safe_octet_boundaries(): void {
		$output = ( new IcsCalendarBuilder() )->build(
			$this->snapshot( description: str_repeat( 'één kalender ', 20 ) )
		);

		foreach ( explode( "\r\n", trim( $output ) ) as $line ) {
			self::assertLessThanOrEqual( 75, strlen( $line ) );
			self::assertSame( 1, preg_match( '//u', $line ) );
		}

		self::assertStringContainsString( "\r\n ", $output );
	}

	/**
	 * Build one deterministic snapshot.
	 *
	 * @param EventDateRange|null $range       Optional range replacement.
	 * @param EventStatus         $status      Event status.
	 * @param string              $title       Event title.
	 * @param string              $description Event description.
	 * @param string              $location    Event location.
	 */
	private function snapshot(
		?EventDateRange $range = null,
		EventStatus $status = EventStatus::SCHEDULED,
		string $title = 'Concert',
		string $description = 'Details at the event page.',
		string $location = 'Town Hall'
	): CalendarExportSnapshot {
		return new CalendarExportSnapshot(
			42,
			OccurrenceIdentity::from( self::UID, 'one-off' ),
			$title,
			'https://example.com/events/concert/',
			$range ?? EventDateRange::from_local(
				'2026-07-16T19:30:00',
				'2026-07-16T21:30:00',
				false,
				'Europe/Brussels'
			),
			$status,
			$description,
			$location,
			1_784_220_000,
			'concert-2026-07-16'
		);
	}
}
