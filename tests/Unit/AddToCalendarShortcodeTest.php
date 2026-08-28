<?php
/**
 * Tests for the atomic add-to-calendar shortcode.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarRenderer;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Shortcode\AddToCalendarShortcode;
use MiMe\WPSimpleEvents\Tests\Support\FakeCalendarExportSnapshotProvider;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

/** Verifies current occurrence and explicit one-off shortcode selection. */
#[CoversClass( AddToCalendarShortcode::class )]
final class AddToCalendarShortcodeTest extends TestCase {
	private const KEY = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

	/** Reset one public current event. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 801,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => 'Series title',
				)
			),
			'https://example.com/events/series/'
		);
		WordPressState::set_singular_event( true, 801 );
	}

	/** Current context forwards its exact occurrence key while explicit ID stays one-off. */
	public function test_distinguishes_current_occurrence_from_explicit_event(): void {
		$contexts = new EventContextResolver();
		$series   = $contexts->resolve_public( 801 );

		self::assertNotNull( $series );
		$occurrences          = new FakeOccurrencePresentationProvider();
		$occurrences->context = OccurrencePresentationFixture::create( $series, self::KEY );
		$route                = new OccurrenceRouteController( $occurrences );
		$query                = new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => EventPostType::POST_TYPE,
				'p'                                  => 801,
				OccurrenceRouteController::QUERY_VAR => self::KEY,
			)
		);

		self::assertNotNull( $route->resolve( $query ) );
		$snapshots           = new FakeCalendarExportSnapshotProvider();
		$snapshots->snapshot = $this->snapshot();
		$shortcode           = new AddToCalendarShortcode(
			new AddToCalendarRenderer( snapshots: $snapshots, routes: $route ),
			new FrontendAssets()
		);

		self::assertStringContainsString( 'Download calendar file', $shortcode->render() );
		self::assertStringContainsString( 'Download calendar file', $shortcode->render( array( 'id' => '801' ) ) );
		self::assertSame(
			array(
				array(
					'event_id'   => 801,
					'public_key' => self::KEY,
				),
				array(
					'event_id'   => 801,
					'public_key' => null,
				),
			),
			$snapshots->requests
		);
	}

	/** Invalid explicit IDs and empty providers never fall back to current context. */
	public function test_rejects_invalid_explicit_selection_and_empty_provider_intent(): void {
		$snapshots           = new FakeCalendarExportSnapshotProvider();
		$snapshots->snapshot = $this->snapshot();
		$shortcode           = new AddToCalendarShortcode( new AddToCalendarRenderer( $snapshots ) );

		self::assertSame( '', $shortcode->render( array( 'id' => '801bad' ) ) );
		self::assertSame( '', $shortcode->render( array( 'providers' => '' ) ) );
		self::assertSame( array(), $snapshots->requests );
	}

	/** Return one deterministic public snapshot. */
	private function snapshot(): CalendarExportSnapshot {
		return new CalendarExportSnapshot(
			801,
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
			'Details.',
			'Town Hall',
			1_784_220_000,
			'concert-2026-07-16'
		);
	}
}
