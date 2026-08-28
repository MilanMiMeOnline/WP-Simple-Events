<?php
/**
 * Tests for public calendar feed boundary validation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPresenter;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadQueryBuilder;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Rest\CalendarFeedController;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceReadGateway;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceTable;
use MiMe\WPSimpleEvents\Tests\Support\FakeProjectedOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Verifies malformed requests are rejected before querying WordPress.
 */
#[CoversClass( CalendarFeedController::class )]
final class CalendarFeedControllerTest extends TestCase {
	private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * ISO boundaries require a complete date-time and explicit timezone.
	 */
	public function test_iso_boundary_validation_is_strict(): void {
		$controller = new CalendarFeedController();

		self::assertTrue( $controller->valid_iso_boundary( '2026-07-01T00:00:00+02:00' ) );
		self::assertTrue( $controller->valid_iso_boundary( '2026-07-01T00:00:00Z' ) );
		self::assertTrue( $controller->valid_iso_boundary( '2026-07-01T00:00:00-14:00' ) );
		self::assertFalse( $controller->valid_iso_boundary( '2026-07-01' ) );
		self::assertFalse( $controller->valid_iso_boundary( '2026-07-01T00:00:00' ) );
		self::assertFalse( $controller->valid_iso_boundary( '2026-07-01T12:00:00+02:00' ) );
		self::assertFalse( $controller->valid_iso_boundary( '2026-07-01T00:00:00+14:01' ) );
		self::assertFalse( $controller->valid_iso_boundary( array() ) );
	}

	/**
	 * Term lists have deterministic total, item and count bounds.
	 */
	public function test_slug_list_validation_is_bounded(): void {
		$controller = new CalendarFeedController();

		self::assertTrue( $controller->valid_slug_list( '' ) );
		self::assertTrue( $controller->valid_slug_list( 'workshops,summer-2026' ) );
		self::assertFalse( $controller->valid_slug_list( str_repeat( 'a', 201 ) ) );
		self::assertFalse( $controller->valid_slug_list( implode( ',', range( 1, 21 ) ) ) );
		self::assertFalse( $controller->valid_slug_list( 'workshops,***' ) );
		self::assertFalse( $controller->valid_slug_list( array( 'workshops' ) ) );
	}

	/** Healthy occurrence mode preserves occurrence identity, overrides and exact totals. */
	public function test_returns_one_exact_occurrence_calendar_page(): void {
		WordPressState::update_post_meta( 42, EventMeta::COLOR_MODE, EventColorMode::CUSTOM->value );
		WordPressState::update_post_meta( 42, EventMeta::COLOR, '#336699' );
		$occurrence = $this->occurrence();
		$provider   = $this->provider( $occurrence );
		$gateway    = new FakeOccurrenceReadGateway( array( $this->row( $occurrence ) ), 7 );
		$controller = $this->occurrence_controller( $gateway, $provider );
		$response   = $controller->get_items( $this->request() );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		$items = $response->get_data();
		self::assertIsArray( $items );
		self::assertCount( 1, $items );
		self::assertSame( self::KEY, $items[0]['id'] );
		self::assertSame( 'Occurrence title', $items[0]['title'] );
		self::assertSame( 'postponed', $items[0]['status'] );
		self::assertSame( '#336699', $items[0]['backgroundColor'] );
		self::assertSame( '/occurrence/' . self::KEY . '/', substr( $items[0]['url'], -45 ) );
		self::assertSame(
			array(
				'X-WP-Total'      => '7',
				'X-WP-TotalPages' => '7',
			),
			$response->get_headers()
		);
	}

	/** A projection row without canonical presentation returns 503 instead of partial data. */
	public function test_occurrence_calendar_fails_closed_when_presentation_is_unavailable(): void {
		$occurrence = $this->occurrence();
		$gateway    = new FakeOccurrenceReadGateway( array( $this->row( $occurrence ) ), 1 );
		$response   = $this->occurrence_controller(
			$gateway,
			new FakeProjectedOccurrencePresentationProvider()
		)->get_items( $this->request() );

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'wpse_occurrence_calendar_unavailable', $response->get_error_code() );
		self::assertSame( array( 'status' => 503 ), $response->get_error_data() );
	}

	/**
	 * Build one healthy occurrence-mode calendar controller.
	 *
	 * @param FakeOccurrenceReadGateway                   $gateway  Deterministic projection rows.
	 * @param FakeProjectedOccurrencePresentationProvider $provider Effective presentation provider.
	 */
	private function occurrence_controller(
		FakeOccurrenceReadGateway $gateway,
		FakeProjectedOccurrencePresentationProvider $provider
	): CalendarFeedController {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, '1' );

		return new CalendarFeedController(
			occurrences: new OccurrenceReadRepository( $this->queries(), $gateway ),
			occurrence_presenter: new OccurrenceCollectionPresenter( recurring: $provider ),
			occurrence_feature: new OccurrenceRouteFeature( true ),
			occurrence_readiness: new OccurrenceReadiness(
				new FakeOccurrenceTable(),
				new FakeOccurrenceCoverageProbe()
			)
		);
	}

	/**
	 * Build one provider with effective occurrence-owned values.
	 *
	 * @param OccurrenceReadModel $occurrence Exact active occurrence row.
	 */
	private function provider( OccurrenceReadModel $occurrence ): FakeProjectedOccurrencePresentationProvider {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => 42,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => '',
					'post_title'    => 'Series title',
				)
			),
			'https://example.com/events/series/'
		);
		WordPressState::update_post_meta( 42, EventMeta::STATUS, 'scheduled' );
		$series = ( new EventContextResolver() )->resolve_public( 42 );

		self::assertNotNull( $series );

		$provider                        = new FakeProjectedOccurrencePresentationProvider();
		$provider->contexts[ self::KEY ] = new OccurrencePresentationContext(
			$series,
			$occurrence,
			'Occurrence title',
			'',
			0,
			'Occurrence venue',
			'',
			'',
			'',
			''
		);

		return $provider;
	}

	/** Build one valid calendar transport request. */
	private function request(): WP_REST_Request {
		$request = new WP_REST_Request();
		$request->set_param( 'start', '2027-01-01T00:00:00+01:00' );
		$request->set_param( 'end', '2027-02-01T00:00:00+01:00' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( 'page', 1 );
		$request->set_param( 'categories', '' );
		$request->set_param( 'tags', '' );

		return $request;
	}

	/** Build one strict recurring projection model. */
	private function occurrence(): OccurrenceReadModel {
		$range = EventDateRange::from_local(
			'2027-01-05T19:00:00',
			'2027-01-05T21:00:00',
			false,
			'Europe/Brussels'
		);

		return OccurrenceReadModel::from_row(
			array(
				'event_id'      => 42,
				'public_key'    => self::KEY,
				'recurrence_id' => '2027-01-05T19:00:00',
				'generation'    => 8,
				'segment_id'    => 0,
				'source'        => 'rule',
				'start_local'   => $range->start_local(),
				'end_local'     => $range->end_local(),
				'start_utc'     => $range->start_utc(),
				'end_utc'       => $range->end_utc(),
				'timezone'      => $range->timezone(),
				'all_day'       => 0,
				'event_status'  => 'postponed',
			)
		);
	}

	/**
	 * Convert one validated occurrence to a fake database row.
	 *
	 * @param OccurrenceReadModel $occurrence Exact active occurrence row.
	 * @return array<string, int|string>
	 */
	private function row( OccurrenceReadModel $occurrence ): array {
		return array(
			'event_id'      => $occurrence->event_id,
			'public_key'    => $occurrence->public_key,
			'recurrence_id' => $occurrence->recurrence_id,
			'generation'    => $occurrence->generation,
			'segment_id'    => $occurrence->segment_id,
			'source'        => $occurrence->source->value,
			'start_local'   => $occurrence->date_range->start_local(),
			'end_local'     => $occurrence->date_range->end_local(),
			'start_utc'     => $occurrence->date_range->start_utc(),
			'end_utc'       => $occurrence->date_range->end_utc(),
			'timezone'      => $occurrence->date_range->timezone(),
			'all_day'       => 0,
			'event_status'  => $occurrence->status->value,
		);
	}

	/** Build the deterministic occurrence SQL planner. */
	private function queries(): OccurrenceReadQueryBuilder {
		return new OccurrenceReadQueryBuilder(
			'wp_wpse_event_occurrences',
			'wp_posts',
			'wp_postmeta',
			'wp_term_relationships',
			'wp_term_taxonomy',
			'wp_terms'
		);
	}
}
