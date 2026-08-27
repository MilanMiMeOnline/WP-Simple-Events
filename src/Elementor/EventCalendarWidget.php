<?php
/**
 * Elementor event calendar widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use MiMe\WPSimpleEvents\Calendar\CalendarAssets;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
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
		return esc_html__( 'Event Calendar', 'mime-simple-events-calendar' );
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
		return array( FrontendAssets::SCRIPT_HANDLE, CalendarAssets::SCRIPT_HANDLE );
	}

	/** Register content and style controls. */
	protected function register_controls(): void {
		$this->start_controls_section(
			'wpse_content',
			array(
				'label' => esc_html__( 'Calendar', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'initial_view',
			array(
				'label'   => esc_html__( 'Desktop view', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'month',
				'options' => $this->view_options(),
			)
		);
		$this->add_control(
			'mobile_view',
			array(
				'label'   => esc_html__( 'Mobile view', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'list',
				'options' => $this->view_options(),
			)
		);
		$this->add_control(
			'initial_date',
			array(
				'label'       => esc_html__( 'Initial date', 'mime-simple-events-calendar' ),
				'description' => esc_html__( 'Optional. Use YYYY-MM-DD to open on a specific date.', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'YYYY-MM-DD',
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => esc_html__( 'Initial categories', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'placeholder' => esc_html__( 'All categories', 'mime-simple-events-calendar' ),
				'options'     => $this->term_options( EventTaxonomies::CATEGORY ),
				'description' => esc_html__( 'Applied when the calendar first loads. Visitors can change categories when visitor filters are shown.', 'mime-simple-events-calendar' ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'       => esc_html__( 'Initial tags', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'placeholder' => esc_html__( 'All tags', 'mime-simple-events-calendar' ),
				'options'     => $this->term_options( EventTaxonomies::TAG ),
				'description' => esc_html__( 'Applied when the calendar first loads. Visitors can change tags when visitor filters are shown.', 'mime-simple-events-calendar' ),
			)
		);
		$this->add_control(
			'filters',
			array(
				'label'        => esc_html__( 'Show visitor filters', 'mime-simple-events-calendar' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( 'Let visitors filter by available event categories and tags. Hidden when no choices are available.', 'mime-simple-events-calendar' ),
			)
		);
		foreach (
			array(
				'show_navigation'    => esc_html__( 'Show previous and next buttons', 'mime-simple-events-calendar' ),
				'show_today'         => esc_html__( 'Show Today button', 'mime-simple-events-calendar' ),
				'show_view_switcher' => esc_html__( 'Show month/list switcher', 'mime-simple-events-calendar' ),
			) as $id => $label
		) {
			$this->add_control(
				$id,
				array(
					'label'        => $label,
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);
		}
		$this->add_control(
			'fallback_heading_level',
			array(
				'label'   => esc_html__( 'Fallback heading level', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => array(
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				),
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
			'month' => esc_html__( 'Month', 'mime-simple-events-calendar' ),
			'list'  => esc_html__( 'List', 'mime-simple-events-calendar' ),
		);
	}

	/** Register theme-inheriting visual controls. */
	private function register_style_controls(): void {
		$this->start_controls_section(
			'wpse_style',
			array(
				'label' => esc_html__( 'Calendar', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'accent_color',
			array(
				'label'     => esc_html__( 'Accent color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-accent: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'accent_text_color',
			array(
				'label'     => esc_html__( 'Accent text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-on-accent: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'border_color',
			array(
				'label'     => esc_html__( 'Border color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-color-border: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'calendar_background_color',
			array(
				'label'     => esc_html__( 'Background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-background: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'calendar_padding',
			array(
				'label'      => esc_html__( 'Calendar padding', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'today_background_color',
			array(
				'label'     => esc_html__( 'Today background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-today: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'hover_background_color',
			array(
				'label'     => esc_html__( 'List hover background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-hover: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'event_background_color',
			array(
				'label'     => esc_html__( 'Event background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-event-background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'event_text_color',
			array(
				'label'     => esc_html__( 'Event text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-event-text: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'calendar_typography',
				'label'    => esc_html__( 'Calendar typography', 'mime-simple-events-calendar' ),
				'selector' => '{{WRAPPER}} .wpse-calendar',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'label'    => esc_html__( 'Button typography', 'mime-simple-events-calendar' ),
				'selector' => '{{WRAPPER}} .wpse-calendar button',
			)
		);
		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Button background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-button-background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Button text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-button-text: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_hover_background_color',
			array(
				'label'     => esc_html__( 'Button hover background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-button-hover-background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_hover_text_color',
			array(
				'label'     => esc_html__( 'Button hover text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-button-hover-text: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .wpse-calendar .fc .fc-button',
			)
		);
		$this->add_responsive_control(
			'button_border_radius',
			array(
				'label'      => esc_html__( 'Button border radius', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-button-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'toolbar_gap',
			array(
				'label'      => esc_html__( 'Mobile toolbar gap', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-calendar' => '--wpse-calendar-toolbar-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}
}
