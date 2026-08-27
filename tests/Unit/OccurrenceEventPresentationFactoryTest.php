<?php
/**
 * Tests for effective occurrence presentation and native rendering.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventDetailsRenderer;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\EventTimezoneDisplaySettings;
use MiMe\WPSimpleEvents\Frontend\NativeTemplateRenderer;
use MiMe\WPSimpleEvents\Frontend\OccurrenceDocumentController;
use MiMe\WPSimpleEvents\Frontend\OccurrenceEventPresentationFactory;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Frontend\OccurrenceSeriesNavigationRenderer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Seo\StructuredDataController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

#[CoversClass( OccurrenceEventPresentationFactory::class )]
#[CoversClass( NativeTemplateRenderer::class )]
#[CoversClass( OccurrenceDocumentController::class )]
#[CoversClass( OccurrenceSeriesNavigationRenderer::class )]
/**
 * Proves that one exact occurrence drives the native public presentation.
 */
final class OccurrenceEventPresentationFactoryTest extends TestCase {
	private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** Effective fields replace occurrence-owned values while series content remains. */
	public function test_builds_one_effective_occurrence_presentation(): void {
		WordPressState::set_option( 'date_format', 'Y-m-d' );
		WordPressState::set_option( 'time_format', 'H:i' );
		WordPressState::set_option( EventTimezoneDisplaySettings::OPTION, true );
		$context      = $this->context();
		$presentation = ( new OccurrenceEventPresentationFactory() )->create(
			$context,
			'https://example.com/events/series/occurrence/' . self::KEY . '/'
		);

		self::assertNotNull( $presentation );
		self::assertSame( 'Occurrence title', $presentation->title );
		self::assertSame( 'Series body', $presentation->event->post_content );
		self::assertSame( 'Occurrence note', $presentation->note );
		self::assertSame( 99, $presentation->featured_image_id );
		self::assertSame( EventStatus::POSTPONED, $presentation->status );
		self::assertSame( '2027-01-05, 19:00 – 21:00', $presentation->date?->label );
		self::assertSame( 'Europe/Brussels (UTC+01:00)', $presentation->date?->timezone_label );
		self::assertSame( 'Occurrence venue', $presentation->venue );
		self::assertSame(
			'https://example.com/events/series/occurrence/' . self::KEY . '/',
			$presentation->permalink
		);
	}

	/** Invalid canonical destinations fail closed before public output is assembled. */
	public function test_rejects_an_invalid_occurrence_canonical(): void {
		self::assertNull(
			( new OccurrenceEventPresentationFactory() )->create( $this->context(), 'javascript:alert(1)' )
		);
	}

	/** An explicit zero image masks the inherited series image without leaking its ID. */
	public function test_explicit_no_image_override_hides_the_series_image(): void {
		$presentation = ( new OccurrenceEventPresentationFactory() )->create(
			$this->context( 0 ),
			'https://example.com/events/series/occurrence/' . self::KEY . '/'
		);

		self::assertNotNull( $presentation );
		self::assertFalse( $presentation->has_featured_image );
		self::assertSame( 0, $presentation->featured_image_id );
		self::assertSame( '', ( new EventFieldRenderer() )->featured_image( $presentation ) );
	}

	/** The native fallback renders exact occurrence fields instead of series schedule fields. */
	public function test_native_single_renders_the_current_occurrence_context(): void {
		WordPressState::set_option( 'date_format', 'Y-m-d' );
		WordPressState::set_option( 'time_format', 'H:i' );
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 99,
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
				)
			),
			'',
			'https://example.com/occurrence.jpg',
			'Occurrence poster'
		);
		$route = $this->resolved_route();

		$output = ( new NativeTemplateRenderer(
			single: new EventDetailsRenderer(),
			occurrences: $route
		) )->render_single_block();

		self::assertStringContainsString( 'Occurrence title', $output );
		self::assertStringNotContainsString( '>Series title<', $output );
		self::assertStringContainsString( '2027-01-05, 19:00 – 21:00', $output );
		self::assertStringContainsString( 'wpse-event-status-postponed', $output );
		self::assertStringContainsString( '<div class="wpse-single-event-content">Series body</div>', $output );
		self::assertStringContainsString( '<div class="wpse-event-note">Occurrence note</div>', $output );
		self::assertStringContainsString( 'https://example.com/occurrence.jpg', $output );
		self::assertStringContainsString( 'Occurrence venue', $output );
		self::assertStringContainsString( 'This date is part of a repeating event.', $output );
		self::assertStringContainsString( 'View the event series', $output );
		self::assertStringContainsString( 'https://example.com/events/series/', $output );
	}

	/** Elementor Theme Builder may own an occurrence page after widget context parity. */
	public function test_occurrence_route_allows_an_applicable_elementor_single_template(): void {
		WordPressState::set_elementor_location( 'single', '<main id="elementor-occurrence">Builder output</main>' );

		$output = ( new NativeTemplateRenderer(
			single: new EventDetailsRenderer(),
			occurrences: $this->resolved_route()
		) )->render_single_block();

		self::assertSame( '<main id="elementor-occurrence">Builder output</main>', $output );
		self::assertStringNotContainsString( 'Occurrence title', $output );
	}

	/** Core title and canonical metadata stay bound to the exact occurrence leaf. */
	public function test_document_metadata_uses_the_current_occurrence(): void {
		$route      = $this->resolved_route();
		$documents  = new OccurrenceDocumentController( $route );
		$series     = $this->context()->series->event;
		$other_post = new WP_Post(
			array(
				'ID' => 88,
			)
		);

		self::assertSame(
			array(
				'title' => 'Occurrence title',
				'site'  => 'Example',
			),
			$documents->title_parts(
				array(
					'title' => 'Series title',
					'site'  => 'Example',
				)
			)
		);
		self::assertSame(
			'https://example.com/events/series/occurrence/' . self::KEY . '/',
			$documents->canonical_url( 'https://example.com/events/series/', $series )
		);
		self::assertSame(
			'https://example.com/other/',
			$documents->canonical_url( 'https://example.com/other/', $other_post )
		);
	}

	/** JSON-LD receives the same effective title, date, URL, note, image and venue. */
	public function test_structured_data_uses_the_current_occurrence(): void {
		WordPressState::set_singular_event( true, 42 );
		WordPressState::set_option( 'date_format', 'Y-m-d' );
		WordPressState::set_option( 'time_format', 'H:i' );
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 99,
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
				)
			),
			'',
			'https://example.com/occurrence.jpg'
		);

		ob_start();
		( new StructuredDataController( occurrences: $this->resolved_route() ) )->render();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'Occurrence title', $output );
		self::assertStringContainsString( 'Occurrence note', $output );
		self::assertStringContainsString( '2027-01-05T19:00:00+01:00', $output );
		self::assertStringContainsString( 'Occurrence venue', $output );
		self::assertStringContainsString( 'https://example.com/occurrence.jpg', $output );
		self::assertStringContainsString(
			'https://example.com/events/series/occurrence/' . self::KEY . '/',
			$output
		);
	}

	/** Return one route that has resolved the exact deterministic occurrence. */
	private function resolved_route(): OccurrenceRouteController {
		$provider          = new FakeOccurrencePresentationProvider();
		$provider->context = $this->context();
		$route             = new OccurrenceRouteController( $provider );
		$query             = new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => EventPostType::POST_TYPE,
				'p'                                  => 42,
				OccurrenceRouteController::QUERY_VAR => self::KEY,
			)
		);

		self::assertNotNull( $route->resolve( $query ) );

		return $route;
	}

	/**
	 * Build one exact context with deliberately different series and occurrence data.
	 *
	 * @param int $featured_image_id Effective occurrence image ID.
	 */
	private function context( int $featured_image_id = 99 ): OccurrencePresentationContext {
		$range      = EventDateRange::from_local(
			'2027-01-05T19:00:00',
			'2027-01-05T21:00:00',
			false,
			'Europe/Brussels'
		);
		$occurrence = OccurrenceReadModel::from_row(
			array(
				'event_id'      => 42,
				'public_key'    => self::KEY,
				'recurrence_id' => '2027-01-05T19:00:00',
				'generation'    => 7,
				'segment_id'    => 0,
				'source'        => 'rule',
				'start_local'   => $range->start_local(),
				'end_local'     => $range->end_local(),
				'start_utc'     => $range->start_utc(),
				'end_utc'       => $range->end_utc(),
				'timezone'      => $range->timezone(),
				'all_day'       => 0,
				'event_status'  => EventStatus::POSTPONED->value,
			)
		);
		$event      = new WP_Post(
			array(
				'ID'            => 42,
				'post_type'     => EventPostType::POST_TYPE,
				'post_status'   => 'publish',
				'post_password' => '',
				'post_title'    => 'Series title',
				'post_content'  => 'Series body',
				'post_excerpt'  => 'Series excerpt',
			)
		);
		$series     = new EventPresentation(
			$event,
			'Series title',
			'https://example.com/events/series/',
			true,
			null,
			EventStatus::SCHEDULED,
			'Series venue',
			'Series address',
			'https://example.com/series-location',
			'https://example.com/series-action',
			'Series action',
			array(),
			array(),
			77
		);

		return new OccurrencePresentationContext(
			$series,
			$occurrence,
			'Occurrence title',
			'Occurrence note',
			$featured_image_id,
			'Occurrence venue',
			'Occurrence address',
			'https://example.com/occurrence-location',
			'https://example.com/occurrence-action',
			'Occurrence action'
		);
	}
}
