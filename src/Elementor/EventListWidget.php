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
		return esc_html__( 'Event List / Grid', 'mime-simple-events-calendar' );
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
				'label' => esc_html__( 'Events', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'view',
			array(
				'label'   => esc_html__( 'Layout', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid' => esc_html__( 'Grid', 'mime-simple-events-calendar' ),
					'list' => esc_html__( 'List', 'mime-simple-events-calendar' ),
				),
			)
		);
		$this->add_control(
			'period',
			array(
				'label'   => esc_html__( 'Period', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'upcoming',
				'options' => array(
					'upcoming' => esc_html__( 'Upcoming', 'mime-simple-events-calendar' ),
					'past'     => esc_html__( 'Past', 'mime-simple-events-calendar' ),
					'all'      => esc_html__( 'All', 'mime-simple-events-calendar' ),
				),
			)
		);
		$this->add_control(
			'limit',
			array(
				'label'   => esc_html__( 'Events per page', 'mime-simple-events-calendar' ),
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
				'label'          => esc_html__( 'Columns', 'mime-simple-events-calendar' ),
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
				'condition'      => array( 'view' => 'grid' ),
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => esc_html__( 'Categories', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'placeholder' => esc_html__( 'All categories', 'mime-simple-events-calendar' ),
				'options'     => $this->term_options( EventTaxonomies::CATEGORY ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'       => esc_html__( 'Tags', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'placeholder' => esc_html__( 'All tags', 'mime-simple-events-calendar' ),
				'options'     => $this->term_options( EventTaxonomies::TAG ),
			)
		);

		foreach ( $this->switcher_controls() as $id => $control ) {
			$this->add_control( $id, $control );
		}
		$this->add_control(
			'excerpt_length',
			array(
				'label'     => esc_html__( 'Excerpt length (words)', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 30,
				'min'       => 1,
				'max'       => 100,
				'step'      => 1,
				'condition' => array( 'show_excerpt' => 'yes' ),
			)
		);
		$this->add_control(
			'heading_level',
			array(
				'label'     => esc_html__( 'Title heading level', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'h3',
				'options'   => $this->heading_options(),
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

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
			'filters'       => $this->switcher( esc_html__( 'Show filters', 'mime-simple-events-calendar' ), false ),
			'pagination'    => $this->switcher( esc_html__( 'Show pagination', 'mime-simple-events-calendar' ), true ),
			'show_image'    => $this->switcher( esc_html__( 'Show image', 'mime-simple-events-calendar' ), true ),
			'show_title'    => $this->switcher( esc_html__( 'Show title', 'mime-simple-events-calendar' ), true ),
			'show_date'     => $this->switcher( esc_html__( 'Show date and time', 'mime-simple-events-calendar' ), true ),
			'show_excerpt'  => $this->switcher( esc_html__( 'Show excerpt', 'mime-simple-events-calendar' ), true ),
			'show_location' => $this->switcher( esc_html__( 'Show location', 'mime-simple-events-calendar' ), true ),
		);
	}

	/**
	 * Return safe card-title heading choices.
	 *
	 * @return array<string, string>
	 */
	private function heading_options(): array {
		return array(
			'h2' => 'H2',
			'h3' => 'H3',
			'h4' => 'H4',
			'h5' => 'H5',
			'h6' => 'H6',
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
				'label' => esc_html__( 'Event cards', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-events' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'muted_color',
			array(
				'label'     => esc_html__( 'Secondary text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-events' => '--wpse-color-muted: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'card_background_color',
			array(
				'label'     => esc_html__( 'Card background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-event-card' => '--wpse-card-background: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'mime-simple-events-calendar' ),
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
		$this->add_responsive_control(
			'card_row_gap',
			array(
				'label'      => esc_html__( 'Row gap', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-events' => '--wpse-grid-row-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'card_column_gap',
			array(
				'label'      => esc_html__( 'Column gap', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-events' => '--wpse-grid-column-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'card_content_padding',
			array(
				'label'      => esc_html__( 'Content padding', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-event-card' => '--wpse-card-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'card_image_ratio',
			array(
				'label'     => esc_html__( 'Image aspect ratio', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'auto'   => esc_html__( 'Original', 'mime-simple-events-calendar' ),
					'16 / 9' => esc_html__( 'Landscape 16:9', 'mime-simple-events-calendar' ),
					'3 / 2'  => esc_html__( 'Landscape 3:2', 'mime-simple-events-calendar' ),
					'1 / 1'  => esc_html__( 'Square 1:1', 'mime-simple-events-calendar' ),
					'4 / 5'  => esc_html__( 'Portrait 4:5', 'mime-simple-events-calendar' ),
				),
				'selectors' => array( '{{WRAPPER}} .wpse-events' => '--wpse-image-ratio: {{VALUE}};' ),
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
				'label'      => esc_html__( 'Border radius', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-events' => '--wpse-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'label'    => esc_html__( 'Event title typography', 'mime-simple-events-calendar' ),
				'selector' => '{{WRAPPER}} .wpse-event-card-title',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'label'    => esc_html__( 'Button and pagination typography', 'mime-simple-events-calendar' ),
				'selector' => '{{WRAPPER}} .wpse-events button, {{WRAPPER}} .wpse-events-pagination a',
			)
		);
		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Button text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpse-events button, {{WRAPPER}} .wpse-events-pagination a, {{WRAPPER}} .wpse-events-pagination span' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Button background color', 'mime-simple-events-calendar' ),
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

		$this->start_controls_section(
			'wpse_filter_style',
			array(
				'label'     => esc_html__( 'Filters', 'mime-simple-events-calendar' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'filters' => 'yes' ),
			)
		);
		$this->add_control(
			'filters_background_color',
			array(
				'label'     => esc_html__( 'Panel background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-events-filters' => '--wpse-filter-background: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'filters_padding',
			array(
				'label'      => esc_html__( 'Panel padding', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-events-filters' => '--wpse-filter-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'filters_border',
				'selector' => '{{WRAPPER}} .wpse-events-filters',
			)
		);
		$this->add_control(
			'control_background_color',
			array(
				'label'     => esc_html__( 'Control background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-events-filters' => '--wpse-control-background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'control_text_color',
			array(
				'label'     => esc_html__( 'Control text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-events-filters' => '--wpse-control-text: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'control_border',
				'selector' => '{{WRAPPER}} .wpse-events-filters select, {{WRAPPER}} .wpse-events-filter-submit button',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'wpse_pagination_style',
			array(
				'label'     => esc_html__( 'Pagination', 'mime-simple-events-calendar' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'pagination' => 'yes' ),
			)
		);
		$this->add_control(
			'pagination_background_color',
			array(
				'label'     => esc_html__( 'Container background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-events-pagination ul.page-numbers' => '--wpse-pagination-background: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'pagination_padding',
			array(
				'label'      => esc_html__( 'Container padding', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-events-pagination ul.page-numbers' => '--wpse-pagination-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'pagination_gap',
			array(
				'label'      => esc_html__( 'Item gap', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-events-pagination ul.page-numbers' => '--wpse-pagination-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'pagination_border',
				'selector' => '{{WRAPPER}} .wpse-events-pagination ul.page-numbers',
			)
		);
		$this->end_controls_section();
	}
}
