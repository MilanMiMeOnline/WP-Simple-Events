<?php
/**
 * Tests for add-to-calendar provider URL construction.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\CalendarExport\CalendarActionBuilder;
use MiMe\WPSimpleEvents\CalendarExport\CalendarActionLink;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportUrlBuilder;
use MiMe\WPSimpleEvents\CalendarExport\CalendarProvider;
use MiMe\WPSimpleEvents\CalendarExport\CalendarProviderUrlBuilder;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Verifies local and external links describe one exact immutable snapshot. */
#[CoversClass( CalendarActionBuilder::class )]
#[CoversClass( CalendarActionLink::class )]
#[CoversClass( CalendarExportUrlBuilder::class )]
#[CoversClass( CalendarProviderUrlBuilder::class )]
final class CalendarActionBuilderTest extends TestCase {
	private const UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/** One-off and recurring ICS routes carry only their required strict identity. */
	public function test_builds_same_origin_one_off_and_occurrence_download_urls(): void {
		$builder   = new CalendarExportUrlBuilder();
		$one_off   = $builder->build( $this->snapshot() );
		$identity  = OccurrenceIdentity::from( self::UID, '2026-07-16T19:30:00' );
		$recurring = $builder->build( $this->snapshot( identity: $identity ) );

		self::assertSame(
			'https://example.com/?wpse_calendar_export=ics&wpse_event=42',
			$one_off
		);
		self::assertSame(
			'https://example.com/?wpse_calendar_export=ics&wpse_event=42&wpse_occurrence=' . $identity->public_key(),
			$recurring
		);
		self::assertSame(
			'https://example.com/?wpse_calendar_export=ics&wpse_event=42',
			$builder->build( $this->snapshot( url: 'http://simpleevents.local/events/concert/' ) )
		);
	}

	/** Google receives UTC dates, bounded public fields and IANA timezone hints. */
	public function test_builds_documented_google_prefill_values(): void {
		$url   = ( new CalendarProviderUrlBuilder() )->build( CalendarProvider::GOOGLE, $this->snapshot() );
		$query = $this->query( $url );

		self::assertStringStartsWith( 'https://calendar.google.com/calendar/render?', $url );
		self::assertSame( 'TEMPLATE', $query['action'] );
		self::assertSame( 'Concert', $query['text'] );
		self::assertSame( '20260716T173000Z/20260716T193000Z', $query['dates'] );
		self::assertSame( 'Details at the event page.', $query['details'] );
		self::assertSame( 'Town Hall', $query['location'] );
		self::assertSame( 'Europe/Brussels', $query['stz'] );
		self::assertSame( 'Europe/Brussels', $query['etz'] );
	}

	/** All-day external links use exclusive local ends without UTC date drift. */
	public function test_builds_truthful_all_day_google_and_outlook_values(): void {
		$range   = EventDateRange::from_local( '2026-10-24', '2026-10-25', true, 'Europe/Brussels' );
		$google  = $this->query(
			( new CalendarProviderUrlBuilder() )->build(
				CalendarProvider::GOOGLE,
				$this->snapshot( range: $range )
			)
		);
		$outlook = $this->query(
			( new CalendarProviderUrlBuilder() )->build(
				CalendarProvider::OUTLOOK,
				$this->snapshot( range: $range )
			)
		);

		self::assertSame( '20261024/20261026', $google['dates'] );
		self::assertSame( '2026-10-24', $outlook['startdt'] );
		self::assertSame( '2026-10-26', $outlook['enddt'] );
		self::assertSame( 'true', $outlook['allday'] );
		self::assertSame( '/calendar/action/compose', $outlook['path'] );
		self::assertSame( 'addevent', $outlook['rru'] );
	}

	/** Zero-duration events retain ICS but omit unverified external providers. */
	public function test_omits_external_links_for_zero_duration_events(): void {
		$range   = EventDateRange::from_local( '2026-07-16T19:30:00', null, false, '+02:00' );
		$actions = ( new CalendarActionBuilder() )->build(
			$this->snapshot( range: $range ),
			array( CalendarProvider::ICS, CalendarProvider::GOOGLE, CalendarProvider::OUTLOOK )
		);

		self::assertCount( 1, $actions );
		self::assertSame( CalendarProvider::ICS, $actions[0]->provider );
		self::assertFalse( $actions[0]->external );
	}

	/** Long provider values stay valid UTF-8 and below the complete URL budget. */
	public function test_bounds_external_provider_urls_after_encoding(): void {
		$snapshot = $this->snapshot(
			title: str_repeat( 'é', 300 ),
			description: str_repeat( 'Details & context ', 400 ),
			location: str_repeat( 'Main hall ', 150 )
		);

		foreach ( array( CalendarProvider::GOOGLE, CalendarProvider::OUTLOOK ) as $provider ) {
			$url = ( new CalendarProviderUrlBuilder() )->build( $provider, $snapshot );

			self::assertNotSame( '', $url );
			self::assertLessThanOrEqual( CalendarProviderUrlBuilder::MAX_URL_BYTES, strlen( $url ) );
			self::assertSame( 1, preg_match( '//u', urldecode( $url ) ) );
		}
	}

	/**
	 * Parse one provider query into decoded scalar values.
	 *
	 * @param string $url Provider URL.
	 * @return array<string, string>
	 */
	private function query( string $url ): array {
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		$items = array();
		parse_str( is_string( $query ) ? $query : '', $items );

		return array_filter( $items, 'is_string' );
	}

	/**
	 * Build one deterministic public snapshot.
	 *
	 * @param OccurrenceIdentity|null $identity    Optional identity.
	 * @param EventDateRange|null     $range       Optional exact range.
	 * @param string                  $title       Public title.
	 * @param string                  $description Public description.
	 * @param string                  $location    Public location.
	 * @param string                  $url         Canonical public URL.
	 */
	private function snapshot(
		?OccurrenceIdentity $identity = null,
		?EventDateRange $range = null,
		string $title = 'Concert',
		string $description = 'Details at the event page.',
		string $location = 'Town Hall',
		string $url = 'https://example.com/events/concert/'
	): CalendarExportSnapshot {
		return new CalendarExportSnapshot(
			42,
			$identity ?? OccurrenceIdentity::from( self::UID, 'one-off' ),
			$title,
			$url,
			$range ?? EventDateRange::from_local(
				'2026-07-16T19:30:00',
				'2026-07-16T21:30:00',
				false,
				'Europe/Brussels'
			),
			EventStatus::SCHEDULED,
			$description,
			$location,
			1_784_220_000,
			'concert-2026-07-16'
		);
	}
}
