<?php
/**
 * Tests for the three thin Elementor widgets.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Calendar\CalendarAssets;
use MiMe\WPSimpleEvents\Elementor\AbstractEventWidget;
use MiMe\WPSimpleEvents\Elementor\EventCalendarWidget;
use MiMe\WPSimpleEvents\Elementor\EventDetailsWidget;
use MiMe\WPSimpleEvents\Elementor\EventListWidget;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Tests\Support\FakeEditorContext;
use MiMe\WPSimpleEvents\Tests\Support\FakeShortcodeRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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
	 * A selected preview event reaches the shared details renderer.
	 */
	public function test_details_widget_delegates_a_valid_preview_event(): void {
		$renderer = new FakeShortcodeRenderer();
		$widget   = new EventDetailsWidget( array(), null, $renderer, new FakeEditorContext( true ) );
		$widget->wpse_set_test_settings( array( 'event_id' => '81' ) );

		self::assertSame( '<div class="rendered">Event output</div>', $this->render( $widget ) );
		self::assertSame( array( 'id' => 81 ), $renderer->attributes );
		self::assertSame( 'wpse-event-details', $widget->get_name() );
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
