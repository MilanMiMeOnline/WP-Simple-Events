<?php
/**
 * Tests for the three thin Elementor widgets.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use Elementor\Widgets_Manager;
use MiMe\WPSimpleEvents\Calendar\CalendarAssets;
use MiMe\WPSimpleEvents\Elementor\AbstractEventWidget;
use MiMe\WPSimpleEvents\Elementor\EventCalendarWidget;
use MiMe\WPSimpleEvents\Elementor\EventDetailsWidget;
use MiMe\WPSimpleEvents\Elementor\EventListWidget;
use MiMe\WPSimpleEvents\Elementor\WidgetRegistrar;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Tests\Support\FakeEditorContext;
use MiMe\WPSimpleEvents\Tests\Support\FakeShortcodeRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass( AbstractEventWidget::class )]
#[CoversClass( EventListWidget::class )]
#[CoversClass( EventCalendarWidget::class )]
#[CoversClass( EventDetailsWidget::class )]
/**
 * Ensures widgets stay presentation-only adapters.
 */
final class ElementorWidgetsTest extends TestCase {
	/**
	 * The list widget maps controls and outputs the shared renderer result.
	 */
	public function test_list_widget_delegates_to_shared_renderer(): void {
		$renderer = new FakeShortcodeRenderer();
		$widget   = new EventListWidget( array(), null, $renderer );
		$widget->wpse_set_test_settings(
			array(
				'view'       => 'list',
				'period'     => 'all',
				'limit'      => 6,
				'columns'    => 2,
				'pagination' => '',
			)
		);

		self::assertSame( '<div class="rendered">Event output</div>', $this->render( $widget ) );
		self::assertIsArray( $renderer->attributes );
		self::assertSame( 'list', $renderer->attributes['view'] );
		self::assertSame( 'all', $renderer->attributes['period'] );
		self::assertSame( 6, $renderer->attributes['limit'] );
		self::assertFalse( $renderer->attributes['pagination'] );
		self::assertSame( array( FrontendAssets::STYLE_HANDLE ), $widget->get_style_depends() );
		self::assertSame( 'wpse-event-list', $widget->get_name() );
	}

	/**
	 * The calendar widget declares both local dependencies and delegates output.
	 */
	public function test_calendar_widget_declares_calendar_dependencies(): void {
		$renderer = new FakeShortcodeRenderer();
		$widget   = new EventCalendarWidget( array(), null, $renderer );
		$widget->wpse_set_test_settings( array( 'initial_view' => 'list' ) );

		self::assertSame( '<div class="rendered">Event output</div>', $this->render( $widget ) );
		self::assertIsArray( $renderer->attributes );
		self::assertSame( 'list', $renderer->attributes['initial_view'] );
		self::assertSame( array( FrontendAssets::STYLE_HANDLE ), $widget->get_style_depends() );
		self::assertSame( array( CalendarAssets::SCRIPT_HANDLE ), $widget->get_script_depends() );
		self::assertSame( 'wpse-event-calendar', $widget->get_name() );
	}

	/**
	 * Calendar and button typography controls have distinct editor labels.
	 */
	public function test_calendar_typography_controls_have_clear_labels(): void {
		$widget = new EventCalendarWidget();
		$method = new ReflectionMethod( $widget, 'register_controls' );
		$method->invoke( $widget );
		$controls = $widget->wpse_test_group_controls();

		self::assertSame( 'Calendar typography', $controls['calendar_typography']['args']['label'] ?? null );
		self::assertSame( 'Button typography', $controls['button_typography']['args']['label'] ?? null );
	}

	/**
	 * Calendar query constraints and visitor controls are clearly distinguished.
	 */
	public function test_calendar_filter_controls_explain_their_scope(): void {
		$widget = new EventCalendarWidget();
		$method = new ReflectionMethod( $widget, 'register_controls' );
		$method->invoke( $widget );
		$controls = $widget->wpse_test_controls();

		self::assertSame( 'Initial categories', $controls['category']['label'] ?? null );
		self::assertSame(
			'Applied when the calendar first loads. Visitors can change categories when visitor filters are shown.',
			$controls['category']['description'] ?? null
		);
		self::assertSame( 'Initial tags', $controls['tag']['label'] ?? null );
		self::assertSame(
			'Applied when the calendar first loads. Visitors can change tags when visitor filters are shown.',
			$controls['tag']['description'] ?? null
		);
		self::assertSame( 'Show visitor filters', $controls['filters']['label'] ?? null );
		self::assertSame( 'yes', $controls['filters']['default'] ?? null );
		self::assertSame(
			'Let visitors filter by available event categories and tags. Hidden when no choices are available.',
			$controls['filters']['description'] ?? null
		);
	}

	/**
	 * List controls cover cards, controls and pagination without custom CSS.
	 */
	public function test_list_widget_exposes_component_scoped_presentation_controls(): void {
		$widget = new EventListWidget();
		$method = new ReflectionMethod( $widget, 'register_controls' );
		$method->invoke( $widget );
		$controls       = $widget->wpse_test_controls();
		$group_controls = $widget->wpse_test_group_controls();

		foreach ( array(
			'card_background_color',
			'card_content_padding',
			'card_row_gap',
			'card_column_gap',
			'card_image_ratio',
			'filters_background_color',
			'filters_padding',
			'control_background_color',
			'control_text_color',
			'pagination_background_color',
			'pagination_padding',
			'pagination_gap',
		) as $control_id ) {
			self::assertArrayHasKey( $control_id, $controls );
			self::assertArrayNotHasKey( 'default', $controls[ $control_id ] );
		}

		foreach ( array( 'card_border', 'filters_border', 'control_border', 'pagination_border' ) as $control_id ) {
			self::assertArrayHasKey( $control_id, $group_controls );
		}
		self::assertSame(
			array( '{{WRAPPER}} .wpse-events-pagination ul.page-numbers' => '--wpse-pagination-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			$controls['pagination_padding']['selectors'] ?? null
		);
		self::assertSame( array( 'view' => 'grid' ), $controls['columns']['condition'] ?? null );
		self::assertSame( array( 'show_excerpt' => 'yes' ), $controls['excerpt_length']['condition'] ?? null );
		self::assertSame( array( 'show_title' => 'yes' ), $controls['heading_level']['condition'] ?? null );
		self::assertSame( 'All categories', $controls['category']['placeholder'] ?? null );
		self::assertSame( 'All tags', $controls['tag']['placeholder'] ?? null );
		self::assertSame( 'Event title typography', $group_controls['title_typography']['args']['label'] ?? null );
		self::assertSame( 'Button and pagination typography', $group_controls['button_typography']['args']['label'] ?? null );
	}

	/**
	 * Calendar controls separate canvas, events and button interaction states.
	 */
	public function test_calendar_widget_exposes_practical_visual_states(): void {
		$widget = new EventCalendarWidget();
		$method = new ReflectionMethod( $widget, 'register_controls' );
		$method->invoke( $widget );
		$controls = $widget->wpse_test_controls();

		foreach ( array(
			'calendar_background_color',
			'calendar_padding',
			'today_background_color',
			'hover_background_color',
			'event_background_color',
			'event_text_color',
			'button_background_color',
			'button_text_color',
			'button_hover_background_color',
			'button_hover_text_color',
			'button_border_radius',
			'toolbar_gap',
		) as $control_id ) {
			self::assertArrayHasKey( $control_id, $controls );
			self::assertArrayNotHasKey( 'default', $controls[ $control_id ] );
		}
		self::assertArrayHasKey( 'button_border', $widget->wpse_test_group_controls() );
		self::assertSame( 'text', $controls['initial_date']['type'] ?? null );
		self::assertSame( 'yes', $controls['show_navigation']['default'] ?? null );
		self::assertSame( 'yes', $controls['show_today']['default'] ?? null );
		self::assertSame( 'yes', $controls['show_view_switcher']['default'] ?? null );
	}

	/**
	 * Composite details expose summary, image and action styling boundaries.
	 */
	public function test_details_widget_exposes_shared_field_style_controls(): void {
		$widget = new EventDetailsWidget();
		$method = new ReflectionMethod( $widget, 'register_controls' );
		$method->invoke( $widget );
		$controls = $widget->wpse_test_controls();
		$groups   = $widget->wpse_test_group_controls();

		foreach ( array(
			'spacing',
			'summary_background_color',
			'summary_padding',
			'summary_border_radius',
			'image_ratio',
			'image_border_radius',
			'action_background_color',
			'action_text_color',
			'action_padding',
			'action_border_radius',
		) as $control_id ) {
			self::assertArrayHasKey( $control_id, $controls );
			self::assertArrayNotHasKey( 'default', $controls[ $control_id ] );
		}
		self::assertArrayHasKey( 'action_border', $widget->wpse_test_group_controls() );

		foreach ( array( 'show_title', 'show_image', 'show_date', 'show_status', 'show_location', 'show_content', 'show_action', 'show_terms' ) as $control_id ) {
			self::assertSame( 'yes', $controls[ $control_id ]['default'] ?? null );
		}
		self::assertSame( array( 'show_title' => 'yes' ), $controls['heading_level']['condition'] ?? null );
		self::assertSame( array( 'show_action' => 'yes' ), $controls['action_label']['condition'] ?? null );
		self::assertSame( 'Event source', $controls['event_id']['label'] ?? null );
		self::assertSame( 'Current event (automatic)', $controls['event_id']['placeholder'] ?? null );
		self::assertSame( 'Event details typography', $groups['details_typography']['args']['label'] ?? null );
		self::assertSame( 'Field label typography', $groups['label_typography']['args']['label'] ?? null );
	}

	/**
	 * A selected preview event reaches the shared details renderer.
	 */
	public function test_details_widget_delegates_a_valid_preview_event(): void {
		$renderer = new FakeShortcodeRenderer();
		$widget   = new EventDetailsWidget( array(), null, $renderer, new FakeEditorContext( true ) );
		$widget->wpse_set_test_settings( array( 'event_id' => '81' ) );

		self::assertSame( '<div class="rendered">Event output</div>', $this->render( $widget ) );
		self::assertSame( 81, $renderer->attributes['id'] ?? null );
		self::assertTrue( $renderer->attributes['show_title'] ?? false );
		self::assertSame( 'wpse-event-details', $widget->get_name() );
	}

	/** Elementor-reconstructed details widgets retain the request-shared adapter. */
	public function test_reconstructed_details_widget_uses_the_registered_runtime_adapter(): void {
		$renderer  = new FakeShortcodeRenderer();
		$registrar = new WidgetRegistrar( details: $renderer );
		$registrar->register_widgets( new Widgets_Manager() );
		$property = new ReflectionProperty( AbstractEventWidget::class, 'renderer' );

		self::assertSame( $renderer, $property->getValue( new EventDetailsWidget() ) );
	}

	/**
	 * Empty details output gets an editor-only instruction, never random data.
	 */
	public function test_details_placeholder_is_limited_to_the_editor(): void {
		$editor_widget = new EventDetailsWidget(
			array(),
			null,
			new FakeShortcodeRenderer( '' ),
			new FakeEditorContext( true )
		);
		$public_widget = new EventDetailsWidget(
			array(),
			null,
			new FakeShortcodeRenderer( '' ),
			new FakeEditorContext( false )
		);

		self::assertStringContainsString( 'Select a public event', $this->render( $editor_widget ) );
		self::assertSame( '', $this->render( $public_widget ) );
	}

	/**
	 * Invoke Elementor's protected server-side render method.
	 *
	 * @param AbstractEventWidget $widget Widget under test.
	 */
	private function render( AbstractEventWidget $widget ): string {
		$method = new ReflectionMethod( $widget, 'render' );
		ob_start();
		$method->invoke( $widget );

		return (string) ob_get_clean();
	}
}
