<?php
/**
 * Tests for the atomic Elementor event-field palette.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Elementor\Widget_Base;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Elementor\AbstractEventFieldWidget;
use MiMe\WPSimpleEvents\Elementor\AtomicWidgetSettings;
use MiMe\WPSimpleEvents\Elementor\AtomicWidgetRuntime;
use MiMe\WPSimpleEvents\Elementor\EventAddressWidget;
use MiMe\WPSimpleEvents\Elementor\EventCategoriesWidget;
use MiMe\WPSimpleEvents\Elementor\EventContentWidget;
use MiMe\WPSimpleEvents\Elementor\EventDateTimeWidget;
use MiMe\WPSimpleEvents\Elementor\EventExcerptWidget;
use MiMe\WPSimpleEvents\Elementor\EventExternalActionWidget;
use MiMe\WPSimpleEvents\Elementor\EventFeaturedImageWidget;
use MiMe\WPSimpleEvents\Elementor\EventLocationLinkWidget;
use MiMe\WPSimpleEvents\Elementor\EventStatusWidget;
use MiMe\WPSimpleEvents\Elementor\EventTagsWidget;
use MiMe\WPSimpleEvents\Elementor\EventTitleWidget;
use MiMe\WPSimpleEvents\Elementor\EventVenueWidget;
use MiMe\WPSimpleEvents\Elementor\PreviewEventOptions;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Tests\Support\FakeEditorContext;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use WP_Post;
use WP_Query;
use WP_Term;

#[CoversClass( AbstractEventFieldWidget::class )]
#[CoversClass( AtomicWidgetSettings::class )]
#[CoversClass( AtomicWidgetRuntime::class )]
#[CoversClass( EventTitleWidget::class )]
#[CoversClass( EventFeaturedImageWidget::class )]
#[CoversClass( EventDateTimeWidget::class )]
#[CoversClass( EventStatusWidget::class )]
#[CoversClass( EventVenueWidget::class )]
#[CoversClass( EventAddressWidget::class )]
#[CoversClass( EventLocationLinkWidget::class )]
#[CoversClass( EventContentWidget::class )]
#[CoversClass( EventExcerptWidget::class )]
#[CoversClass( EventExternalActionWidget::class )]
#[CoversClass( EventCategoriesWidget::class )]
#[CoversClass( EventTagsWidget::class )]
/**
 * Protects source resolution, field parity, controls and empty states.
 */
final class ElementorAtomicWidgetsTest extends TestCase {
	/** Reset and create one complete public event. */
	protected function setUp(): void {
		WordPressState::reset();
		$this->add_complete_event( 91 );
	}

	/**
	 * Every dedicated widget consumes the matching named field renderer.
	 *
	 * @param string $widget_class Widget class.
	 * @param string $expected     Expected semantic output.
	 */
	#[DataProvider( 'field_widgets' )]
	public function test_every_atomic_widget_renders_its_public_field( string $widget_class, string $expected ): void {
		$widget = $this->widget( $widget_class, false );
		$widget->wpse_set_test_settings( array( 'event_id' => '91' ) );

		self::assertStringContainsString( $expected, $this->render( $widget ) );
		self::assertSame( array( FrontendAssets::STYLE_HANDLE ), $widget->get_style_depends() );
		self::assertFalse( $widget->has_widget_inner_wrapper() );
	}

	/**
	 * Supply every atomic widget and its semantic marker.
	 *
	 * @return array<string, array{class-string<AbstractEventFieldWidget>, string}>
	 */
	public static function field_widgets(): array {
		return array(
			'title'           => array( EventTitleWidget::class, 'wpse-single-event-title' ),
			'featured image'  => array( EventFeaturedImageWidget::class, 'wpse-single-event-image' ),
			'date and time'   => array( EventDateTimeWidget::class, 'wpse-event-date' ),
			'status'          => array( EventStatusWidget::class, 'wpse-event-status-cancelled' ),
			'venue'           => array( EventVenueWidget::class, 'wpse-event-venue' ),
			'address'         => array( EventAddressWidget::class, 'wpse-event-address' ),
			'location link'   => array( EventLocationLinkWidget::class, 'wpse-event-location-link' ),
			'content'         => array( EventContentWidget::class, 'wpse-single-event-content' ),
			'excerpt'         => array( EventExcerptWidget::class, 'wpse-event-excerpt' ),
			'external action' => array( EventExternalActionWidget::class, 'wpse-event-action' ),
			'categories'      => array( EventCategoriesWidget::class, 'wpse-event-categories' ),
			'tags'            => array( EventTagsWidget::class, 'wpse-event-tags' ),
		);
	}

	/** Explicit and current sources keep identical field semantics. */
	public function test_static_selection_and_current_context_render_identically(): void {
		WordPressState::set_singular_event( true, 91 );
		$contexts = new EventContextResolver();
		$fields   = new EventFieldRenderer();
		$explicit = $this->widget( EventVenueWidget::class, false, $contexts, $fields );
		$current  = $this->widget( EventVenueWidget::class, false, $contexts, $fields );
		$settings = array(
			'show_label' => 'yes',
			'label'      => 'Place <b>now</b>:',
		);
		$explicit->wpse_set_test_settings(
			array(
				...$settings,
				'event_id' => 91,
			)
		);
		$current->wpse_set_test_settings( $settings );

		self::assertSame( $this->render( $explicit ), $this->render( $current ) );
		self::assertStringContainsString( 'Place now:', $this->render( $current ) );
		self::assertStringNotContainsString( '<b>', $this->render( $current ) );
	}

	/** Current widgets use exact occurrence fields while selected events remain series sources. */
	public function test_current_occurrence_and_explicit_series_sources_remain_distinct(): void {
		WordPressState::set_singular_event( true, 91 );
		WordPressState::set_option( 'date_format', 'Y-m-d' );
		WordPressState::set_option( 'time_format', 'H:i' );
		$contexts = new EventContextResolver();
		$route    = $this->occurrence_route( 91, $contexts );
		$current  = new CurrentEventPresentationResolver( $contexts, $route );
		$output   = '';

		foreach ( array_keys( self::field_widgets() ) as $field ) {
			$definition = self::field_widgets()[ $field ];
			$widget     = $this->widget(
				$definition[0],
				false,
				$contexts,
				new EventFieldRenderer(),
				$current
			);
			$widget->wpse_set_test_settings( array() );
			$output .= $this->render( $widget );
		}

		self::assertStringContainsString( 'Occurrence block title', $output );
		self::assertStringContainsString( '2027-01-05, 19:00 – 21:00', $output );
		self::assertStringContainsString( 'wpse-event-status-postponed', $output );
		self::assertStringContainsString( 'Occurrence block venue', $output );
		self::assertStringContainsString( 'Occurrence block address', $output );
		self::assertStringContainsString( 'https://example.com/occurrence-location', $output );
		self::assertStringContainsString( 'https://example.com/occurrence-action', $output );
		self::assertStringContainsString( 'Complete description', $output );
		self::assertStringContainsString( 'Short summary', $output );
		self::assertStringContainsString( 'Music', $output );
		self::assertStringContainsString( 'Live', $output );
		self::assertStringNotContainsString( 'poster.jpg', $output );

		$explicit = $this->widget( EventTitleWidget::class, false, $contexts, new EventFieldRenderer(), $current );
		$explicit->wpse_set_test_settings( array( 'event_id' => 91 ) );
		self::assertStringContainsString( 'Summer event', $this->render( $explicit ) );
		self::assertStringNotContainsString( 'Occurrence block title', $this->render( $explicit ) );
	}

	/** Invalid and inaccessible selections never fall back or leak on the frontend. */
	public function test_explicit_sources_are_strictly_public_and_password_free(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 92,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => 'Draft secret',
				)
			)
		);
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => 93,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => 'secret',
					'post_title'    => 'Protected secret',
				)
			)
		);
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 94,
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'Blog post',
				)
			)
		);
		WordPressState::set_singular_event( true, 91 );

		foreach ( array( 'bad', '92', '93', '94' ) as $event_id ) {
			$widget = $this->widget( EventTitleWidget::class, false );
			$widget->wpse_set_test_settings( array( 'event_id' => $event_id ) );
			self::assertSame( '', $this->render( $widget ) );
		}
	}

	/** Missing fields explain themselves only inside the editor. */
	public function test_empty_field_placeholder_is_editor_only(): void {
		$editor = $this->widget( EventStatusWidget::class, true );
		$public = $this->widget( EventStatusWidget::class, false );
		$editor->wpse_set_test_settings( array( 'event_id' => 91 ) );
		$public->wpse_set_test_settings( array( 'event_id' => 91 ) );
		WordPressState::update_post_meta( 91, EventMeta::STATUS, EventStatus::SCHEDULED->value );

		self::assertStringContainsString( 'no public value', $this->render( $editor ) );
		self::assertSame( '', $this->render( $public ) );
	}

	/** Field-specific settings are allowlisted and controls remain scoped. */
	public function test_image_and_label_controls_are_field_specific_and_safe(): void {
		$image = $this->widget( EventFeaturedImageWidget::class, false );
		$image->wpse_set_test_settings(
			array(
				'event_id'   => 91,
				'image_size' => '../../raw',
				'alt_mode'   => 'decorative',
				'link'       => 'yes',
			)
		);

		$output = $this->render( $image );
		self::assertStringContainsString( 'attachment-large size-large', $output );
		self::assertStringContainsString( 'alt=""', $output );
		self::assertStringContainsString( 'wpse-event-image-link', $output );

		$controls_method = new ReflectionMethod( $image, 'register_controls' );
		$controls_method->invoke( $image );
		$controls = $image->wpse_test_controls();
		self::assertArrayHasKey( 'event_id', $controls );
		self::assertArrayHasKey( 'image_size', $controls );
		self::assertArrayHasKey( 'alt_mode', $controls );
		self::assertArrayHasKey( 'link', $controls );
		self::assertArrayHasKey( 'spacing', $controls );
		self::assertArrayNotHasKey( 'meta_key', $controls );
	}

	/** Action controls sanitize overrides but preserve event-specific defaults. */
	public function test_action_and_label_overrides_are_sanitized(): void {
		$action = $this->widget( EventExternalActionWidget::class, false );
		$action->wpse_set_test_settings(
			array(
				'event_id'  => 91,
				'link_text' => 'Parking <script>x</script>plan',
			)
		);

		self::assertStringContainsString( '>Parking plan</a>', $this->render( $action ) );

		$action->wpse_set_test_settings( array( 'event_id' => 91 ) );
		self::assertStringContainsString( '>Register now</a>', $this->render( $action ) );
	}

	/**
	 * Image and action widgets expose field-specific visual controls through shared selectors.
	 */
	public function test_image_and_action_widgets_expose_practical_style_controls(): void {
		$image        = $this->widget( EventFeaturedImageWidget::class, false );
		$image_method = new ReflectionMethod( $image, 'register_controls' );
		$image_method->invoke( $image );
		$image_controls = $image->wpse_test_controls();

		foreach ( array( 'image_width', 'image_ratio', 'image_border_radius' ) as $control_id ) {
			self::assertArrayHasKey( $control_id, $image_controls );
			self::assertArrayNotHasKey( 'default', $image_controls[ $control_id ] );
		}

		$action        = $this->widget( EventExternalActionWidget::class, false );
		$action_method = new ReflectionMethod( $action, 'register_controls' );
		$action_method->invoke( $action );
		$action_controls = $action->wpse_test_controls();

		foreach ( array( 'background_color', 'hover_background_color', 'hover_text_color', 'padding', 'border_radius' ) as $control_id ) {
			self::assertArrayHasKey( $control_id, $action_controls );
			self::assertArrayNotHasKey( 'default', $action_controls[ $control_id ] );
		}
		self::assertArrayHasKey( 'action_border', $action->wpse_test_group_controls() );
	}

	/** Reconstructed widget objects share request-local presentation services. */
	public function test_default_widget_objects_share_request_runtime_services(): void {
		$title = new EventTitleWidget();
		$venue = new EventVenueWidget();

		foreach ( array( 'contexts', 'fields', 'current' ) as $property_name ) {
			$property = new ReflectionProperty( AbstractEventFieldWidget::class, $property_name );
			self::assertSame( $property->getValue( $title ), $property->getValue( $venue ) );
		}
	}

	/**
	 * Create one widget with explicitly shared test services.
	 *
	 * @param string                                $widget_class Widget class.
	 * @param bool                                  $editing      Editor state.
	 * @param EventContextResolver|null             $contexts     Optional shared resolver.
	 * @param EventFieldRenderer|null               $fields       Optional shared renderer.
	 * @param CurrentEventPresentationResolver|null $current Optional occurrence resolver.
	 */
	private function widget(
		string $widget_class,
		bool $editing,
		?EventContextResolver $contexts = null,
		?EventFieldRenderer $fields = null,
		?CurrentEventPresentationResolver $current = null
	): AbstractEventFieldWidget {
		return new $widget_class(
			array(),
			null,
			$contexts ?? new EventContextResolver(),
			$fields ?? new EventFieldRenderer(),
			new FakeEditorContext( $editing ),
			new PreviewEventOptions(),
			$current
		);
	}

	/**
	 * Resolve one deterministic occurrence route over an existing event.
	 *
	 * @param int                  $event_id Event post ID.
	 * @param EventContextResolver $contexts Shared series resolver.
	 */
	private function occurrence_route( int $event_id, EventContextResolver $contexts ): OccurrenceRouteController {
		$key      = 'dddddddddddddddddddddddddddddddd';
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
	 * Invoke Elementor's protected server renderer.
	 *
	 * @param Widget_Base $widget Widget under test.
	 */
	private function render( Widget_Base $widget ): string {
		$method = new ReflectionMethod( $widget, 'render' );
		ob_start();
		$method->invoke( $widget );

		return (string) ob_get_clean();
	}

	/**
	 * Create one complete event fixture for the whole atomic palette.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function add_complete_event( int $event_id ): void {
		$event = new WP_Post(
			array(
				'ID'           => $event_id,
				'post_type'    => EventPostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Summer event',
				'post_excerpt' => 'Short summary',
				'post_content' => '<p>Complete description</p>',
			)
		);
		WordPressState::add_post( $event, 'https://example.com/events/summer/', 'https://example.com/poster.jpg', 'Event poster' );
		$timezone = new DateTimeZone( 'Europe/Brussels' );
		WordPressState::update_post_meta( $event_id, EventMeta::START_UTC, ( new DateTimeImmutable( '2026-07-20 12:00:00', $timezone ) )->getTimestamp() );
		WordPressState::update_post_meta( $event_id, EventMeta::END_UTC, ( new DateTimeImmutable( '2026-07-20 14:00:00', $timezone ) )->getTimestamp() );
		WordPressState::update_post_meta( $event_id, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( $event_id, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( $event_id, EventMeta::STATUS, EventStatus::CANCELLED->value );
		WordPressState::update_post_meta( $event_id, EventMeta::VENUE, 'Main Hall' );
		WordPressState::update_post_meta( $event_id, EventMeta::ADDRESS, "High Street 1\nBrussels" );
		WordPressState::update_post_meta( $event_id, EventMeta::LOCATION_URL, 'https://example.com/route/' );
		WordPressState::update_post_meta( $event_id, EventMeta::EVENT_URL, 'https://example.com/register/' );
		WordPressState::update_post_meta( $event_id, EventMeta::EVENT_URL_LABEL, 'Register now' );
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 31,
					'name'    => 'Music',
					'slug'    => 'music',
				)
			),
			'https://example.com/event-category/music/'
		);
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 32,
					'name'    => 'Live',
					'slug'    => 'live',
				)
			),
			'https://example.com/event-tag/live/'
		);
		WordPressState::set_post_terms( $event_id, EventTaxonomies::CATEGORY, array( 31 ) );
		WordPressState::set_post_terms( $event_id, EventTaxonomies::TAG, array( 32 ) );
	}
}
