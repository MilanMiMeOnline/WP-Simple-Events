<?php
/**
 * Tests for the first native Divi 5 event module.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Divi\DiviModuleSettings;
use MiMe\WPSimpleEvents\Divi\EventTitleModuleRenderer;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

#[CoversClass( DiviModuleSettings::class )]
#[CoversClass( EventTitleModuleRenderer::class )]
/** Protects source resolution, allowlists and public access rules. */
final class DiviEventTitleModuleTest extends TestCase {
	/** Reset the WordPress doubles before each assertion. */
	protected function setUp(): void {
		WordPressState::reset();
		$this->add_event( 201, 'Public concert', 'publish' );
	}

	/** Explicit public selections render through the shared semantic field layer. */
	public function test_renders_an_explicit_public_event_with_allowlisted_settings(): void {
		$output = $this->renderer()->render(
			array(
				'event' => array(
					'innerContent' => array(
						'desktop' => array(
							'value' => array(
								'eventId'   => '201',
								'linkTitle' => 'on',
							),
						),
					),
				),
				'title' => array(
					'decoration' => array(
						'font' => array(
							'font' => array(
								'desktop' => array( 'value' => array( 'headingLevel' => 'h3' ) ),
							),
						),
					),
				),
			)
		);

		self::assertSame(
			'<h3 class="wpse-single-event-title"><a href="https://example.com/events/concert/">Public concert</a></h3>',
			$output
		);
	}

	/** Current context works while explicit draft selection remains fail-closed. */
	public function test_current_context_is_supported_but_explicit_drafts_are_hidden(): void {
		$this->add_event( 202, 'Editorial draft', 'draft' );
		WordPressState::set_singular_event( true, 201 );

		self::assertStringContainsString( 'Public concert', $this->renderer()->render( array() ) );
		self::assertSame(
			'',
			$this->renderer()->render(
				array(
					'event' => array(
						'innerContent' => array(
							'desktop' => array( 'value' => array( 'eventId' => '202' ) ),
						),
					),
				)
			)
		);
	}

	/** Exact occurrence routes override current context without changing explicit series selections. */
	public function test_current_occurrence_context_remains_distinct_from_explicit_series_selection(): void {
		WordPressState::set_singular_event( true, 201 );
		$contexts = new EventContextResolver();
		$route    = $this->occurrence_route( 201, $contexts );
		$renderer = new EventTitleModuleRenderer(
			$contexts,
			new CurrentEventPresentationResolver( $contexts, $route ),
			new EventFieldRenderer()
		);

		$current = $renderer->render( array() );
		$series  = $renderer->render(
			array(
				'event' => array(
					'innerContent' => array(
						'desktop' => array( 'value' => array( 'eventId' => '201' ) ),
					),
				),
			)
		);

		self::assertStringContainsString( 'Occurrence block title', $current );
		self::assertStringNotContainsString( 'Public concert', $current );
		self::assertStringContainsString( 'Public concert', $series );
		self::assertStringNotContainsString( 'Occurrence block title', $series );
	}

	/** Invalid nested values fall back to current, h2 and an unlinked title. */
	public function test_invalid_settings_are_allowlisted(): void {
		$attrs = array(
			'event' => array(
				'innerContent' => array(
					'desktop' => array(
						'value' => array(
							'eventId'   => '-4',
							'linkTitle' => 'yes',
						),
					),
				),
			),
			'title' => array(
				'decoration' => array(
					'font' => array(
						'font' => array(
							'desktop' => array( 'value' => array( 'headingLevel' => 'script' ) ),
						),
					),
				),
			),
		);

		self::assertSame( 0, DiviModuleSettings::event_id( $attrs ) );
		self::assertSame( 'h2', DiviModuleSettings::heading( $attrs ) );
		self::assertFalse( DiviModuleSettings::link_title( $attrs ) );
	}

	/** Build the host-neutral module renderer under test. */
	private function renderer(): EventTitleModuleRenderer {
		$contexts = new EventContextResolver();

		return new EventTitleModuleRenderer(
			$contexts,
			new CurrentEventPresentationResolver( $contexts ),
			new EventFieldRenderer()
		);
	}

	/**
	 * Resolve one deterministic virtual occurrence route for the current event.
	 *
	 * @param int                  $event_id Current event post ID.
	 * @param EventContextResolver $contexts Shared event context resolver.
	 */
	private function occurrence_route( int $event_id, EventContextResolver $contexts ): OccurrenceRouteController {
		$key      = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
		$series   = $contexts->resolve_public( $event_id );
		$provider = new FakeOccurrencePresentationProvider();

		self::assertNotNull( $series );
		$provider->context = OccurrencePresentationFixture::create( $series, $key );
		$route             = new OccurrenceRouteController( $provider );
		$query             = new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => EventPostType::POST_TYPE,
				'p'                                  => $event_id,
				OccurrenceRouteController::QUERY_VAR => $key,
			)
		);

		self::assertNotNull( $route->resolve( $query ) );

		return $route;
	}

	/**
	 * Add one deterministic event post.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $title    Event title.
	 * @param string $status   WordPress post status.
	 */
	private function add_event( int $event_id, string $title, string $status ): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => $event_id,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => $status,
					'post_title'  => $title,
				)
			),
			'https://example.com/events/concert/'
		);
	}
}
