<?php
/**
 * Tests for conditional Elementor integration and widget registration.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use Elementor\Elements_Manager;
use Elementor\Widgets_Manager;
use MiMe\WPSimpleEvents\Elementor\ElementorIntegration;
use MiMe\WPSimpleEvents\Elementor\EventCalendarWidget;
use MiMe\WPSimpleEvents\Elementor\EventDetailsWidget;
use MiMe\WPSimpleEvents\Elementor\EventListWidget;
use MiMe\WPSimpleEvents\Elementor\WidgetRegistrar;
use MiMe\WPSimpleEvents\Tests\Support\FakeElementorHost;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ElementorIntegration::class )]
#[CoversClass( WidgetRegistrar::class )]
/**
 * Verifies that Elementor remains optional and modern hooks are used.
 */
final class ElementorIntegrationTest extends TestCase {
	/** Reset recorded hooks before every test. */
	protected function setUp(): void {
		HookRecorder::reset();
	}

	/**
	 * Missing Elementor causes one deferred hook and no widget class loading.
	 */
	public function test_missing_elementor_only_registers_loaded_listener(): void {
		$integration = new ElementorIntegration( $this->registrar(), new FakeElementorHost( false, null ) );

		$integration->register();

		self::assertIsCallable( HookRecorder::action( 'elementor/loaded' ) );
		self::assertNull( HookRecorder::action( 'elementor/widgets/register' ) );
		self::assertNull( HookRecorder::action( 'elementor/elements/categories_registered' ) );
	}

	/**
	 * A compatible, already-loaded host receives only current registration hooks.
	 */
	public function test_supported_elementor_registers_modern_hooks_immediately(): void {
		$integration = new ElementorIntegration( $this->registrar(), new FakeElementorHost( true, '4.1.5' ) );

		$integration->register();

		self::assertIsCallable( HookRecorder::action( 'elementor/widgets/register' ) );
		self::assertIsCallable( HookRecorder::action( 'elementor/elements/categories_registered' ) );
		self::assertNull( HookRecorder::action( 'elementor/loaded' ) );
	}

	/**
	 * An old host leaves every native plugin feature untouched but adds no widgets.
	 */
	public function test_unsupported_elementor_does_not_register_widgets(): void {
		$integration = new ElementorIntegration( $this->registrar(), new FakeElementorHost( true, '3.34.9' ) );

		$integration->register();

		self::assertNull( HookRecorder::action( 'elementor/widgets/register' ) );
		self::assertNull( HookRecorder::action( 'elementor/elements/categories_registered' ) );
	}

	/**
	 * The three compatible widgets and complete atomic palette are registered.
	 */
	public function test_registrar_registers_the_required_widgets_and_category(): void {
		$registrar = $this->registrar();
		$widgets   = new Widgets_Manager();
		$elements  = new Elements_Manager();

		$registrar->register_widgets( $widgets );
		$registrar->register_category( $elements );

		self::assertCount( 15, $widgets->registered );
		self::assertInstanceOf( EventListWidget::class, $widgets->registered[0] );
		self::assertInstanceOf( EventCalendarWidget::class, $widgets->registered[1] );
		self::assertInstanceOf( EventDetailsWidget::class, $widgets->registered[2] );
		self::assertSame(
			array(
				'wpse-event-title',
				'wpse-event-featured-image',
				'wpse-event-date-time',
				'wpse-event-status',
				'wpse-event-venue',
				'wpse-event-address',
				'wpse-event-location-link',
				'wpse-event-content',
				'wpse-event-excerpt',
				'wpse-event-external-action',
				'wpse-event-categories',
				'wpse-event-tags',
			),
			array_map( static fn ( object $widget ): string => $widget->get_name(), array_slice( $widgets->registered, 3 ) )
		);
		self::assertSame( 'Simple Events by MiMe', $elements->categories[ WidgetRegistrar::CATEGORY ]['title'] );
	}

	/** Create a registrar with the same shared service shape as production. */
	private function registrar(): WidgetRegistrar {
		return new WidgetRegistrar();
	}
}
