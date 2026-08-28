<?php
/**
 * Tests for the primary Gutenberg event components.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Blocks\EventCompositeBlockDefinitions;
use MiMe\WPSimpleEvents\Blocks\EventCompositeBlockRenderer;
use MiMe\WPSimpleEvents\Blocks\EventCompositeBlockSettings;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventDetailsRenderer;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\RecordingShortcodeRenderer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Block;
use WP_Post;
use WP_Query;

#[CoversClass( EventCompositeBlockDefinitions::class )]
#[CoversClass( EventCompositeBlockRenderer::class )]
#[CoversClass( EventCompositeBlockSettings::class )]
/** Protects metadata, strict settings and shared renderer delegation. */
final class EventCompositeBlocksTest extends TestCase {
	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** Metadata registers three dynamic blocks without changing atomic names. */
	public function test_metadata_defines_the_three_primary_components(): void {
		self::assertSame( array( 'event-list', 'event-calendar', 'event-details' ), EventCompositeBlockDefinitions::slugs() );

		foreach ( EventCompositeBlockDefinitions::slugs() as $slug ) {
			$path     = dirname( __DIR__, 2 ) . '/blocks/' . $slug . '/block.json';
			$metadata = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads one trusted local test fixture.

			self::assertSame( 'wpse/' . $slug, $metadata['name'] ?? null );
			self::assertSame( 3, $metadata['apiVersion'] ?? null );
			self::assertSame( 'wpse-event-fields-editor', $metadata['editorScript'] ?? null );
			self::assertSame( 'wpse-frontend', $metadata['style'] ?? null );
			self::assertFalse( $metadata['supports']['html'] ?? true );
		}

		$details = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/blocks/event-details/block.json' ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads one trusted local test fixture.
		self::assertSame( array( 'postId', 'postType' ), $details['usesContext'] ?? null );
	}

	/** Block settings map strictly to the established shortcode attribute contract. */
	public function test_settings_are_bounded_and_allowlisted(): void {
		self::assertSame(
			array(
				'view'                  => 'list',
				'period'                => 'all',
				'limit'                 => 50,
				'columns'               => 4,
				'category'              => array( 'music', 'family' ),
				'tag'                   => array( 'live' ),
				'filters'               => true,
				'pagination'            => false,
				'show_excerpt'          => false,
				'show_image'            => true,
				'show_location'         => false,
				'show_title'            => false,
				'show_date'             => true,
				'excerpt_length'        => 18,
				'heading_level'         => 'h4',
				'filter_categories'     => true,
				'filter_tags'           => true,
				'filter_layout'         => 'auto',
				'filter_disclosure'     => 'auto',
				'filter_chips'          => true,
				'filter_results'        => false,
				'filter_label'          => '',
				'filter_period_label'   => '',
				'filter_category_label' => '',
				'filter_tag_label'      => '',
				'filter_apply_label'    => '',
			),
			EventCompositeBlockSettings::event_list(
				array(
					'view'          => 'list',
					'period'        => 'all',
					'limit'         => 50,
					'columns'       => 4,
					'categories'    => array( 'Music', 'family', 'Music' ),
					'tags'          => array( 'Live' ),
					'filters'       => true,
					'pagination'    => false,
					'showExcerpt'   => false,
					'showImage'     => true,
					'showLocation'  => false,
					'showTitle'     => false,
					'showDate'      => true,
					'excerptLength' => 18,
					'headingLevel'  => 'h4',
				)
			)
		);

		self::assertSame(
			array(
				'initial_view'           => 'month',
				'mobile_view'            => 'list',
				'category'               => array(),
				'tag'                    => array(),
				'filters'                => true,
				'initial_date'           => '',
				'show_navigation'        => true,
				'show_today'             => true,
				'show_view_switcher'     => true,
				'fallback_heading_level' => 'h3',
				'filter_categories'      => true,
				'filter_tags'            => true,
				'filter_layout'          => 'auto',
				'filter_disclosure'      => 'auto',
				'filter_chips'           => true,
				'filter_results'         => true,
				'filter_label'           => '',
				'filter_period_label'    => '',
				'filter_category_label'  => '',
				'filter_tag_label'       => '',
				'filter_apply_label'     => '',
			),
			EventCompositeBlockSettings::calendar(
				array(
					'initialView' => '<script>',
					'mobileView'  => 12,
					'categories'  => 'not-an-array',
					'tags'        => array( array( 'bad' ) ),
					'filters'     => 'yes',
				)
			)
		);

		self::assertSame(
			array(
				'show_title'       => false,
				'show_image'       => true,
				'show_date'        => true,
				'show_status'      => true,
				'show_location'    => true,
				'show_content'     => true,
				'show_action'      => true,
				'show_terms'       => true,
				'heading_level'    => 'h1',
				'date_label'       => 'When',
				'venue_label'      => '',
				'location_label'   => '',
				'action_label'     => '',
				'categories_label' => '',
				'tags_label'       => '',
			),
			EventCompositeBlockSettings::details(
				array(
					'showTitle'    => false,
					'showImage'    => 'false',
					'headingLevel' => 'script',
					'dateLabel'    => " When\n<script> ",
				)
			)
		);
		self::assertNull( EventCompositeBlockSettings::event_id( '15' ) );
		self::assertSame( 15, EventCompositeBlockSettings::event_id( 15 ) );
	}

	/** Collection blocks delegate their normalized values to native shortcode renderers. */
	public function test_collection_blocks_delegate_to_shared_shortcode_renderers(): void {
		$list     = new RecordingShortcodeRenderer( '<section class="native-list">List</section>' );
		$calendar = new RecordingShortcodeRenderer( '<section class="native-calendar">Calendar</section>' );
		$renderer = new EventCompositeBlockRenderer( $list, $calendar );

		$list_output     = $renderer->render(
			array(
				'limit'                 => 7,
				'filterPanelBackground' => '#AABBCC',
				'filterFieldText'       => '#112233',
				'filterGap'             => 16,
				'filterOptionGap'       => 9,
				'filterActionText'      => 'red;display:none',
			),
			'',
			new WP_Block( array( 'blockName' => 'wpse/event-list' ) )
		);
		$calendar_output = $renderer->render(
			array( 'initialView' => 'list' ),
			'',
			new WP_Block( array( 'blockName' => 'wpse/event-calendar' ) )
		);

		self::assertSame( 7, $list->attributes['limit'] ?? null );
		self::assertSame( 'list', $calendar->attributes['initial_view'] ?? null );
		self::assertStringContainsString( 'wpse-event-composite-block-list', $list_output );
		self::assertStringContainsString( 'native-list', $list_output );
		self::assertStringContainsString( '--wpse-filter-panel-background:#aabbcc', $list_output );
		self::assertStringContainsString( '--wpse-control-text:#112233', $list_output );
		self::assertStringContainsString( '--wpse-filter-gap:16px', $list_output );
		self::assertStringContainsString( '--wpse-filter-option-gap:9px', $list_output );
		self::assertStringNotContainsString( 'display:none', $list_output );
		self::assertStringContainsString( 'wpse-event-composite-block-calendar', $calendar_output );
		self::assertSame( '', $renderer->render( array(), '', new WP_Block( array( 'blockName' => 'wpse/not-public' ) ) ) );
	}

	/** Details accepts current event context but fails closed for a non-public explicit source. */
	public function test_details_context_and_explicit_visibility_share_native_rules(): void {
		$this->add_event( 501, 'publish', 'Public block event' );
		$this->add_event( 502, 'draft', 'Draft block event' );
		$renderer = new EventCompositeBlockRenderer(
			new RecordingShortcodeRenderer( '' ),
			new RecordingShortcodeRenderer( '' ),
			new EventDetailsRenderer()
		);

		$context_output = $renderer->render(
			array( 'eventId' => 0 ),
			'',
			new WP_Block(
				array( 'blockName' => 'wpse/event-details' ),
				array(
					'postId'   => 501,
					'postType' => EventPostType::POST_TYPE,
				)
			)
		);

		self::assertStringContainsString( 'Public block event', $context_output );
		self::assertSame(
			'',
			$renderer->render(
				array( 'eventId' => 502 ),
				'',
				new WP_Block( array( 'blockName' => 'wpse/event-details' ) )
			)
		);
	}

	/** Details uses the exact occurrence only for its current-context source. */
	public function test_details_distinguishes_current_occurrence_from_explicit_series(): void {
		$this->add_event( 503, 'publish', 'Series details title' );
		$contexts = new EventContextResolver();
		$series   = $contexts->resolve_public( 503 );

		self::assertNotNull( $series );
		$key               = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
		$provider          = new FakeOccurrencePresentationProvider();
		$provider->context = OccurrencePresentationFixture::create( $series, $key );
		$route             = new OccurrenceRouteController( $provider );
		$query             = new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => EventPostType::POST_TYPE,
				'p'                                  => 503,
				OccurrenceRouteController::QUERY_VAR => $key,
			)
		);

		self::assertNotNull( $route->resolve( $query ) );
		$renderer = new EventCompositeBlockRenderer(
			new RecordingShortcodeRenderer( '' ),
			new RecordingShortcodeRenderer( '' ),
			new EventDetailsRenderer( contexts: $contexts ),
			new CurrentEventPresentationResolver( $contexts, $route )
		);
		$block    = new WP_Block(
			array( 'blockName' => 'wpse/event-details' ),
			array(
				'postId'   => 503,
				'postType' => EventPostType::POST_TYPE,
			)
		);

		$current = $renderer->render( array( 'eventId' => 0 ), '', $block );
		self::assertStringContainsString( 'Occurrence block title', $current );
		self::assertStringContainsString( 'Occurrence block note', $current );
		self::assertStringContainsString( 'Occurrence block venue', $current );

		$explicit = $renderer->render( array( 'eventId' => 503 ), '', $block );
		self::assertStringContainsString( 'Series details title', $explicit );
		self::assertStringNotContainsString( 'Occurrence block title', $explicit );
	}

	/**
	 * Add one minimally complete event.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $status   WordPress post status.
	 * @param string $title    Event title.
	 */
	private function add_event( int $event_id, string $status, string $title ): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => $event_id,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => $status,
					'post_title'  => $title,
				)
			),
			'https://example.com/events/' . $event_id . '/'
		);
		WordPressState::update_post_meta( $event_id, EventMeta::START_UTC, 1_784_544_000 );
		WordPressState::update_post_meta( $event_id, EventMeta::END_UTC, 1_784_547_600 );
		WordPressState::update_post_meta( $event_id, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( $event_id, EventMeta::TIMEZONE, 'Europe/Brussels' );
	}
}
