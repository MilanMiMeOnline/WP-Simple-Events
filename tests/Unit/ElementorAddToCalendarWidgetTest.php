<?php
/**
 * Tests for the Elementor add-to-calendar widget.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use Elementor\Widget_Base;
use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarRenderer;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Elementor\AddToCalendarWidget;
use MiMe\WPSimpleEvents\Elementor\PreviewEventOptions;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Tests\Support\FakeCalendarExportSnapshotProvider;
use MiMe\WPSimpleEvents\Tests\Support\FakeEditorContext;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WP_Post;

/** Protects Elementor source parity, controls and editor-only empty states. */
#[CoversClass( AddToCalendarWidget::class )]
final class ElementorAddToCalendarWidgetTest extends TestCase {
	/** Reset one public event in current context. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 831,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => 'Elementor calendar event',
				)
			)
		);
		WordPressState::set_singular_event( true, 831 );
	}

	/** Defaults expose one local ICS action from either supported source mode. */
	public function test_renders_default_ics_for_current_and_explicit_sources(): void {
		$snapshots           = new FakeCalendarExportSnapshotProvider();
		$snapshots->snapshot = $this->snapshot();
		$current             = $this->widget( $snapshots, false );
		$explicit            = $this->widget( $snapshots, false );
		$current->wpse_set_test_settings( array() );
		$explicit->wpse_set_test_settings( array( 'event_id' => '831' ) );

		self::assertStringContainsString( 'wpse-add-to-calendar-ics', $this->render( $current ) );
		self::assertStringContainsString( 'wpse-add-to-calendar-ics', $this->render( $explicit ) );
		self::assertSame(
			array(
				array(
					'event_id'   => 831,
					'public_key' => null,
				),
				array(
					'event_id'   => 831,
					'public_key' => null,
				),
			),
			$snapshots->requests
		);
		self::assertSame( array( FrontendAssets::STYLE_HANDLE ), $current->get_style_depends() );
		self::assertFalse( $current->has_widget_inner_wrapper() );
	}

	/** External providers are explicit and use the shared sanitized layout contract. */
	public function test_normalizes_provider_layout_and_label_controls(): void {
		$snapshots           = new FakeCalendarExportSnapshotProvider();
		$snapshots->snapshot = $this->snapshot();
		$widget              = $this->widget( $snapshots, false );
		$widget->wpse_set_test_settings(
			array(
				'event_id'         => 831,
				'provider_ics'     => '',
				'provider_google'  => 'yes',
				'provider_outlook' => 'yes',
				'layout'           => 'list',
				'label'            => 'Save <script>x</script> date',
			)
		);

		$output = $this->render( $widget );

		self::assertStringNotContainsString( 'wpse-add-to-calendar-ics', $output );
		self::assertStringContainsString( 'wpse-add-to-calendar-google', $output );
		self::assertStringContainsString( 'wpse-add-to-calendar-outlook', $output );
		self::assertStringContainsString( 'wpse-add-to-calendar-list', $output );
		self::assertStringContainsString( 'Save date', $output );
	}

	/** Empty and invalid configurations stay frontend-silent but explain editor state. */
	public function test_empty_state_is_editor_only(): void {
		$snapshots           = new FakeCalendarExportSnapshotProvider();
		$snapshots->snapshot = $this->snapshot();
		$public              = $this->widget( $snapshots, false );
		$editor              = $this->widget( $snapshots, true );
		$settings            = array(
			'event_id'         => '831bad',
			'provider_ics'     => '',
			'provider_google'  => '',
			'provider_outlook' => '',
		);
		$public->wpse_set_test_settings( $settings );
		$editor->wpse_set_test_settings( $settings );

		self::assertSame( '', $this->render( $public ) );
		self::assertStringContainsString( 'Select a public event', $this->render( $editor ) );
		self::assertSame( array(), $snapshots->requests );
	}

	/** Content and style controls remain bounded and wrapper scoped. */
	public function test_registers_practical_content_and_style_controls(): void {
		$widget = $this->widget( new FakeCalendarExportSnapshotProvider(), false );
		( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
		$controls = $widget->wpse_test_controls();

		foreach (
			array(
				'event_id',
				'provider_ics',
				'provider_google',
				'provider_outlook',
				'layout',
				'label',
				'action_background',
				'action_text',
				'action_border',
				'menu_background',
				'action_padding',
				'action_radius',
				'action_gap',
				'menu_padding',
			) as $control_id
		) {
			self::assertArrayHasKey( $control_id, $controls );
		}

		self::assertArrayHasKey( 'calendar_action_border', $widget->wpse_test_group_controls() );
		self::assertArrayHasKey( 'calendar_action_typography', $widget->wpse_test_group_controls() );
		self::assertSame( 'wpse-add-to-calendar', $widget->get_name() );
		self::assertSame( 'eicon-calendar', $widget->get_icon() );
	}

	/**
	 * Build one widget over the configured shared snapshot provider.
	 *
	 * @param FakeCalendarExportSnapshotProvider $snapshots Configured snapshot provider.
	 * @param bool                               $editing   Elementor editor state.
	 */
	private function widget( FakeCalendarExportSnapshotProvider $snapshots, bool $editing ): AddToCalendarWidget {
		return new AddToCalendarWidget(
			array(),
			null,
			new AddToCalendarRenderer( $snapshots ),
			new FakeEditorContext( $editing ),
			new PreviewEventOptions()
		);
	}

	/**
	 * Invoke Elementor's protected renderer.
	 *
	 * @param Widget_Base $widget Widget under test.
	 */
	private function render( Widget_Base $widget ): string {
		$method = new ReflectionMethod( $widget, 'render' );
		ob_start();
		$method->invoke( $widget );

		return (string) ob_get_clean();
	}

	/** Return one deterministic public snapshot. */
	private function snapshot(): CalendarExportSnapshot {
		return new CalendarExportSnapshot(
			831,
			OccurrenceIdentity::from( '019c1d83-1798-4fac-a66d-ae8d67c46319', 'one-off' ),
			'Concert',
			'https://example.com/events/concert/',
			EventDateRange::from_local( '2026-07-16T19:30:00', '2026-07-16T21:30:00', false, 'Europe/Brussels' ),
			EventStatus::SCHEDULED,
			'Details.',
			'Town Hall',
			1_784_220_000,
			'concert-2026-07-16'
		);
	}
}
