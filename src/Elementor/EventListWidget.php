<?php
/**
 * Elementor event list/grid widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Shortcode\EventListShortcode;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

/**
 * Maps Elementor controls to the native event-list renderer.
 */
final class EventListWidget extends AbstractEventWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-list';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event List / Grid', 'wp-simple-events' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-post-list';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'events', 'calendar', 'list', 'grid' );
	}

	/** Register content and style controls. */
	protected function register_controls(): void {
		$this->start_controls_section(
			'wpse_content',
			array(
				'label' => esc_html__( 'Events', 'wp-simple-events' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'view',
			array(
				'label'   => esc_html__( 'Layout', 'wp-simple-events' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid' => esc_html__( 'Grid', 'wp-simple-events' ),
					'list' => esc_html__( 'List', 'wp-simple-events' ),
				),
			)
		);
		$this->add_control(
			'period',
			array(
				'label'   => esc_html__( 'Period', 'wp-simple-events' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'upcoming',
				'options' => array(
					'upcoming' => esc_html__( 'Upcoming', 'wp-simple-events' ),
					'past'     => esc_html__( 'Past', 'wp-simple-events' ),
					'all'      => esc_html__( 'All', 'wp-simple-events' ),
				),
			)
		);
		$this->add_control(
			'limit',
			array(
				'label'   => esc_html__( 'Events per page', 'wp-simple-events' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 12,
				'min'     => 1,
				'max'     => 50,
				'step'    => 1,
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => esc_html__( 'Columns', 'wp-simple-events' ),
				'type'           => Controls_Manager::NUMBER,
				'default'        => 3,
				'tablet_default' => 2,
				'mobile_default' => 1,
				'min'            => 1,
				'max'            => 4,
				'step'           => 1,
				'selectors'      => array(
					'{{WRAPPER}} .wpse-events-view-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				),
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => esc_html__( 'Categories', 'wp-simple-events' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->term_options( EventTaxonomies::CATEGORY ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'       => esc_html__( 'Tags', 'wp-simple-events' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->term_options( EventTaxonomies::TAG ),
			)
		);

		foreach ( $this->switcher_controls() as $id => $control ) {
			$this->add_control( $id, $control );
		}

		$this->end_controls_section();
		$this->register_style_controls();
	}

	/** Render through the shared shortcode instance. */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$settings = is_array( $settings ) ? $settings : array();

		echo $this->renderer->render( WidgetSettings::event_list( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared native renderer owns contextual escaping.
	}

	/** Create a renderer when Elementor reconstructs the widget. */
	protected function default_renderer(): ShortcodeRenderer {
		return new EventListShortcode();
	}

	/**
	 * Return common visibility controls with contract-aligned defaults.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function switcher_controls(): array {
		return array(
			'filters'       => $this->switcher( esc_html__( 'Show filters', 'wp-simple-events' ), false ),
			'pagination'    => $this->switcher( esc_html__( 'Show pagination', 'wp-simple-events' ), true ),
			'show_image'    => $this->switcher( esc_html__( 'Show image', 'wp-simple-events' ), true ),
			'show_excerpt'  => $this->switcher( esc_html__( 'Show excerpt', 'wp-simple-events' ), true ),
			'show_location' => $this->switcher( esc_html__( 'Show location', 'wp-simple-events' ), true ),
		);
	}

	/**
	 * Build one Elementor switcher definition.
	 *
	 * @param string $label   Translated label.
	 * @param bool   $enabled Default switch state.
	 * @return array<string, mixed>
	 */
	private function switcher( string $label, bool $enabled ): array {
		return array(
			'label'        => $label,
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => $enabled ? 'yes' : '',
		);
	}

	/** Register theme-inheriting visual controls. */
	private function register_style_controls(): void {
		$this->start_controls_section(
			'wpse_style',
			array(
				'label' => esc_html__( 'Event cards', 'wp-simple-events' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text color', 'wp-simple-events' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-events' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'muted_color',
			array(
				'label'     => esc_html__( 'Secondary text color', 'wp-simple-events' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-events' => '--wpse-color-muted: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'wp-simple-events' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array(
					'px'  => array(
						'min' => 0,
						'max' => 80,
					),
					'rem' => array(
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .wpse-events' => '--wpse-spacing: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .wpse-event-card',
			)
		);
		$this->add_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'wp-simple-events' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-events' => '--wpse-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .wpse-event-card-title',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .wpse-events button, {{WRAPPER}} .wpse-events-pagination a',
			)
		);
		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Button text color', 'wp-simple-events' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpse-events button, {{WRAPPER}} .wpse-events-pagination a, {{WRAPPER}} .wpse-events-pagination span' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Button background color', 'wp-simple-events' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpse-events button, {{WRAPPER}} .wpse-events-pagination a, {{WRAPPER}} .wpse-events-pagination span' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .wpse-events button, {{WRAPPER}} .wpse-events-pagination a, {{WRAPPER}} .wpse-events-pagination span',
			)
		);
		$this->end_controls_section();
	}
}
