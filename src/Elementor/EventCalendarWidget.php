<?php
/**
 * Elementor event calendar widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use MiMe\WPSimpleEvents\Calendar\CalendarAssets;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Shortcode\CalendarShortcode;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

/**
 * Maps Elementor controls to the accessible native calendar renderer.
 */
final class EventCalendarWidget extends AbstractEventWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-calendar';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Calendar', 'simple-events-by-mime' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-calendar';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'events', 'calendar', 'month', 'list' );
	}

	/**
	 * Declare the local calendar bundle.
	 *
	 * @return string[]
	 */
	public function get_script_depends(): array {
		return array( CalendarAssets::SCRIPT_HANDLE );
	}

	/** Register content and style controls. */
	protected function register_controls(): void {
		$this->start_controls_section(
			'wpse_content',
			array(
				'label' => esc_html__( 'Calendar', 'simple-events-by-mime' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'initial_view',
			array(
				'label'   => esc_html__( 'Desktop view', 'simple-events-by-mime' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'month',
				'options' => $this->view_options(),
			)
		);
		$this->add_control(
			'mobile_view',
			array(
				'label'   => esc_html__( 'Mobile view', 'simple-events-by-mime' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'list',
				'options' => $this->view_options(),
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => esc_html__( 'Initial categories', 'simple-events-by-mime' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->term_options( EventTaxonomies::CATEGORY ),
				'description' => esc_html__( 'Applied when the calendar first loads. Visitors can change categories when visitor filters are shown.', 'simple-events-by-mime' ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'       => esc_html__( 'Initial tags', 'simple-events-by-mime' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->term_options( EventTaxonomies::TAG ),
				'description' => esc_html__( 'Applied when the calendar first loads. Visitors can change tags when visitor filters are shown.', 'simple-events-by-mime' ),
			)
		);
		$this->add_control(
			'filters',
			array(
				'label'        => esc_html__( 'Show visitor filters', 'simple-events-by-mime' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( 'Let visitors filter by available event categories and tags. Hidden when no choices are available.', 'simple-events-by-mime' ),
			)
		);
		$this->end_controls_section();
		$this->register_style_controls();
	}

	/** Render through the shared shortcode instance. */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$settings = is_array( $settings ) ? $settings : array();

		echo $this->renderer->render( WidgetSettings::calendar( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared native renderer owns contextual escaping.
	}

	/** Create a renderer when Elementor reconstructs the widget. */
	protected function default_renderer(): ShortcodeRenderer {
		return new CalendarShortcode();
	}

	/**
	 * Return the supported calendar views.
	 *
	 * @return array<string, string>
	 */
	private function view_options(): array {
		return array(
			'month' => esc_html__( 'Month', 'simple-events-by-mime' ),
			'list'  => esc_html__( 'List', 'simple-events-by-mime' ),
		);
	}

	/** Register theme-inheriting visual controls. */
	private function register_style_controls(): void {
		$this->start_controls_section(
			'wpse_style',
			array(
				'label' => esc_html__( 'Calendar', 'simple-events-by-mime' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text color', 'simple-events-by-mime' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'accent_color',
			array(
				'label'     => esc_html__( 'Accent color', 'simple-events-by-mime' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-accent: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'accent_text_color',
			array(
				'label'     => esc_html__( 'Accent text color', 'simple-events-by-mime' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-on-accent: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'border_color',
			array(
				'label'     => esc_html__( 'Border color', 'simple-events-by-mime' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-color-border: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'calendar_typography',
				'label'    => esc_html__( 'Calendar typography', 'simple-events-by-mime' ),
				'selector' => '{{WRAPPER}} .wpse-calendar',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'label'    => esc_html__( 'Button typography', 'simple-events-by-mime' ),
				'selector' => '{{WRAPPER}} .wpse-calendar button',
			)
		);
		$this->end_controls_section();
	}
}
