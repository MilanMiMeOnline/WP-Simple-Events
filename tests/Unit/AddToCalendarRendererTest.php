<?php
/**
 * Tests for shared semantic add-to-calendar rendering.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarOptions;
use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarRenderer;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\CalendarExport\CalendarProvider;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Tests\Support\FakeCalendarExportSnapshotProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Proves direct, dropdown and list layouts share safe provider semantics. */
#[CoversClass( AddToCalendarOptions::class )]
#[CoversClass( AddToCalendarRenderer::class )]
final class AddToCalendarRendererTest extends TestCase {
	/** Defaults render one direct, same-origin and downloadable ICS action. */
	public function test_renders_one_direct_local_download_by_default(): void {
		$provider           = new FakeCalendarExportSnapshotProvider();
		$provider->snapshot = $this->snapshot();
		$output             = ( new AddToCalendarRenderer( $provider ) )->render_public(
			42,
			AddToCalendarOptions::defaults()
		);

		self::assertStringContainsString( 'wpse-add-to-calendar-direct', $output );
		self::assertStringContainsString( 'wpse-add-to-calendar-ics', $output );
		self::assertStringContainsString( 'Download calendar file', $output );
		self::assertStringContainsString( ' download>', $output );
		self::assertStringNotContainsString( 'target="_blank"', $output );
	}

	/** Multiple providers use native disclosure and isolate external navigation. */
	public function test_renders_accessible_dropdown_with_external_isolation(): void {
		$provider           = new FakeCalendarExportSnapshotProvider();
		$provider->snapshot = $this->snapshot();
		$options            = AddToCalendarOptions::from_input( 'outlook,ics,google', 'dropdown', 'Save this date' );
		$output             = ( new AddToCalendarRenderer( $provider ) )->render_public( 42, $options );

		self::assertStringStartsWith( '<details', $output );
		self::assertStringContainsString( '<summary class="wpse-add-to-calendar-summary">Save this date</summary>', $output );
		self::assertStringContainsString( 'Add to Google Calendar', $output );
		self::assertStringContainsString( 'Add to Outlook', $output );
		self::assertSame( 2, substr_count( $output, 'target="_blank"' ) );
		self::assertSame( 2, substr_count( $output, 'rel="noopener noreferrer"' ) );
		self::assertSame( 2, substr_count( $output, 'referrerpolicy="no-referrer"' ) );
		self::assertLessThan( strpos( $output, 'wpse-add-to-calendar-google' ), strpos( $output, 'wpse-add-to-calendar-ics' ) );
	}

	/** List layout escapes its sanitized label and preserves provider order. */
	public function test_renders_separate_list_with_bounded_plain_label(): void {
		$provider           = new FakeCalendarExportSnapshotProvider();
		$provider->snapshot = $this->snapshot();
		$options            = AddToCalendarOptions::from_input(
			array( 'google', 'invalid', CalendarProvider::ICS, 'google' ),
			'list',
			'<strong>Calendar choices</strong>'
		);
		$output             = ( new AddToCalendarRenderer( $provider ) )->render_public( 42, $options );

		self::assertSame( array( CalendarProvider::ICS, CalendarProvider::GOOGLE ), $options->providers );
		self::assertSame( 'Calendar choices', $options->label );
		self::assertStringStartsWith( '<div class="wpse-add-to-calendar wpse-add-to-calendar-list">', $output );
		self::assertStringNotContainsString( '<strong>', $output );
		self::assertLessThan( strpos( $output, 'wpse-add-to-calendar-google' ), strpos( $output, 'wpse-add-to-calendar-ics' ) );
	}

	/** Empty provider intent and ineligible snapshots render no misleading shell. */
	public function test_omits_empty_or_ineligible_components(): void {
		$provider = new FakeCalendarExportSnapshotProvider();
		$renderer = new AddToCalendarRenderer( $provider );

		self::assertSame( '', $renderer->render_public( 42, AddToCalendarOptions::defaults() ) );

		$provider->snapshot = $this->snapshot();
		$provider->requests = array();
		self::assertSame( '', $renderer->render_public( 42, AddToCalendarOptions::from_input( '' ) ) );
		self::assertSame( array(), $provider->requests );
	}

	/** Return one deterministic public snapshot. */
	private function snapshot(): CalendarExportSnapshot {
		return new CalendarExportSnapshot(
			42,
			OccurrenceIdentity::from( '019c1d83-1798-4fac-a66d-ae8d67c46319', 'one-off' ),
			'Concert',
			'https://example.com/events/concert/',
			EventDateRange::from_local(
				'2026-07-16T19:30:00',
				'2026-07-16T21:30:00',
				false,
				'Europe/Brussels'
			),
			EventStatus::SCHEDULED,
			'Details at the event page.',
			'Town Hall',
			1_784_220_000,
			'concert-2026-07-16'
		);
	}
}
