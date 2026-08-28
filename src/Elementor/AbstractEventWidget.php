<?php
/**
 * Shared Elementor widget adapter behaviour.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

/**
 * Keeps all widgets on the same renderer, asset and taxonomy boundaries.
 */
abstract class AbstractEventWidget extends Widget_Base {
	/**
	 * Shared native shortcode renderer.
	 *
	 * @var ShortcodeRenderer
	 */
	protected ShortcodeRenderer $renderer;

	/**
	 * Create an Elementor widget while preserving the host constructor contract.
	 *
	 * @param mixed                  $data     Elementor widget data.
	 * @param mixed                  $args     Elementor widget arguments.
	 * @param ShortcodeRenderer|null $renderer Shared native renderer.
	 */
	public function __construct( $data = array(), $args = null, ?ShortcodeRenderer $renderer = null ) { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- The first two parameters mirror Elementor's deliberately untyped API.
		$this->renderer = $renderer ?? $this->default_renderer();

		parent::__construct( $data, $args );
	}

	/**
	 * Return the dedicated Elementor category.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( WidgetRegistrar::CATEGORY );
	}

	/**
	 * Declare the native event stylesheet.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( FrontendAssets::STYLE_HANDLE );
	}

	/**
	 * Opt into Elementor's optimized DOM without its optional inner wrapper.
	 */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Build bounded taxonomy choices for editor controls.
	 *
	 * @param string $taxonomy Event taxonomy name.
	 * @return array<string, string>
	 */
	protected function term_options( string $taxonomy ): array {
		if ( ! in_array( $taxonomy, array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG ), true ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 100,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}

		return $options;
	}

	/**
	 * Register the shared bounded filter-content contract.
	 *
	 * @param bool $default_results Host-compatible result default.
	 */
	protected function register_filter_content_controls( bool $default_results ): void {
		$this->start_controls_section(
			'wpse_filter_content',
			array(
				'label'     => esc_html__( 'Visitor filters', 'mime-simple-events-calendar' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => array( 'filters' => 'yes' ),
			)
		);

		foreach (
			array(
				'filter_categories' => array( esc_html__( 'Show categories', 'mime-simple-events-calendar' ), true ),
				'filter_tags'       => array( esc_html__( 'Show tags', 'mime-simple-events-calendar' ), true ),
				'filter_chips'      => array( esc_html__( 'Show active filter chips', 'mime-simple-events-calendar' ), true ),
				'filter_results'    => array( esc_html__( 'Show result status', 'mime-simple-events-calendar' ), $default_results ),
			) as $id => $control
		) {
			$this->add_control(
				$id,
				array(
					'label'        => $control[0],
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => $control[1] ? 'yes' : '',
				)
			);
		}

		$this->add_control(
			'filter_layout',
			array(
				'label'   => esc_html__( 'Filter layout', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'       => esc_html__( 'Automatic', 'mime-simple-events-calendar' ),
					'horizontal' => esc_html__( 'Horizontal', 'mime-simple-events-calendar' ),
					'stacked'    => esc_html__( 'Stacked', 'mime-simple-events-calendar' ),
				),
			)
		);
		$this->add_control(
			'filter_disclosure',
			array(
				'label'   => esc_html__( 'Initial filter panel', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'   => esc_html__( 'Automatic', 'mime-simple-events-calendar' ),
					'open'   => esc_html__( 'Open', 'mime-simple-events-calendar' ),
					'closed' => esc_html__( 'Closed', 'mime-simple-events-calendar' ),
				),
			)
		);

		foreach (
			array(
				'filter_label'          => esc_html__( 'Filter button label', 'mime-simple-events-calendar' ),
				'filter_period_label'   => esc_html__( 'Period label', 'mime-simple-events-calendar' ),
				'filter_category_label' => esc_html__( 'Categories label', 'mime-simple-events-calendar' ),
				'filter_tag_label'      => esc_html__( 'Tags label', 'mime-simple-events-calendar' ),
				'filter_apply_label'    => esc_html__( 'Apply button label', 'mime-simple-events-calendar' ),
			) as $id => $label
		) {
			$this->add_control(
				$id,
				array(
					'label'       => $label,
					'type'        => Controls_Manager::TEXT,
					'placeholder' => esc_html__( 'Use translated default', 'mime-simple-events-calendar' ),
				)
			);
		}

		$this->end_controls_section();
	}

	/** Register one component-scoped filter design system for every primary widget. */
	protected function register_filter_style_controls(): void {
		$this->start_controls_section(
			'wpse_filter_style',
			array(
				'label'     => esc_html__( 'Visitor filters', 'mime-simple-events-calendar' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'filters' => 'yes' ),
			)
		);

		foreach (
			array(
				'filters_background_color'  => array( esc_html__( 'Container background', 'mime-simple-events-calendar' ), '--wpse-filter-background' ),
				'filter_panel_background'   => array( esc_html__( 'Panel background', 'mime-simple-events-calendar' ), '--wpse-filter-panel-background' ),
				'filter_trigger_background' => array( esc_html__( 'Trigger background', 'mime-simple-events-calendar' ), '--wpse-filter-trigger-background' ),
				'filter_trigger_text'       => array( esc_html__( 'Trigger text', 'mime-simple-events-calendar' ), '--wpse-filter-trigger-text' ),
				'control_background_color'  => array( esc_html__( 'Field background', 'mime-simple-events-calendar' ), '--wpse-control-background' ),
				'control_text_color'        => array( esc_html__( 'Field text', 'mime-simple-events-calendar' ), '--wpse-control-text' ),
				'filter_accent'             => array( esc_html__( 'Checkbox accent', 'mime-simple-events-calendar' ), '--wpse-filter-accent' ),
				'filter_chip_background'    => array( esc_html__( 'Chip background', 'mime-simple-events-calendar' ), '--wpse-filter-chip-background' ),
				'filter_chip_text'          => array( esc_html__( 'Chip text', 'mime-simple-events-calendar' ), '--wpse-filter-chip-text' ),
				'filter_action_background'  => array( esc_html__( 'Action background', 'mime-simple-events-calendar' ), '--wpse-filter-action-background' ),
				'filter_action_text'        => array( esc_html__( 'Action text', 'mime-simple-events-calendar' ), '--wpse-filter-action-text' ),
				'filter_status_background'  => array( esc_html__( 'Status background', 'mime-simple-events-calendar' ), '--wpse-filter-status-background' ),
				'filter_status_text'        => array( esc_html__( 'Status text', 'mime-simple-events-calendar' ), '--wpse-filter-status-text' ),
			) as $id => $control
		) {
			$this->add_control(
				$id,
				array(
					'label'     => $control[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => $control[1] . ': {{VALUE}};' ),
				)
			);
		}

		foreach (
			array(
				'filters_padding'        => array( esc_html__( 'Container padding', 'mime-simple-events-calendar' ), '--wpse-filter-padding' ),
				'filter_panel_padding'   => array( esc_html__( 'Panel padding', 'mime-simple-events-calendar' ), '--wpse-filter-panel-padding' ),
				'filter_trigger_padding' => array( esc_html__( 'Trigger padding', 'mime-simple-events-calendar' ), '--wpse-filter-trigger-padding' ),
				'filter_chip_padding'    => array( esc_html__( 'Chip padding', 'mime-simple-events-calendar' ), '--wpse-filter-chip-padding' ),
				'filter_action_padding'  => array( esc_html__( 'Action padding', 'mime-simple-events-calendar' ), '--wpse-filter-action-padding' ),
				'filter_status_padding'  => array( esc_html__( 'Status padding', 'mime-simple-events-calendar' ), '--wpse-filter-status-padding' ),
			) as $id => $control
		) {
			$this->add_responsive_control(
				$id,
				array(
					'label'      => $control[0],
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', 'rem' ),
					'selectors'  => array( '{{WRAPPER}}' => $control[1] . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
		}

		foreach (
			array(
				'filter_gap'                => array( esc_html__( 'Filter gap', 'mime-simple-events-calendar' ), '--wpse-filter-gap', 0, 80 ),
				'filter_option_gap'         => array( esc_html__( 'Option gap', 'mime-simple-events-calendar' ), '--wpse-filter-option-gap', 0, 40 ),
				'filter_checkbox_size'      => array( esc_html__( 'Checkbox size', 'mime-simple-events-calendar' ), '--wpse-filter-checkbox-size', 8, 40 ),
				'filter_options_max_height' => array( esc_html__( 'Option list maximum height', 'mime-simple-events-calendar' ), '--wpse-filter-options-max-height', 80, 800 ),
				'filter_panel_radius'       => array( esc_html__( 'Panel radius', 'mime-simple-events-calendar' ), '--wpse-filter-panel-radius', 0, 80 ),
				'filter_trigger_radius'     => array( esc_html__( 'Trigger radius', 'mime-simple-events-calendar' ), '--wpse-filter-trigger-radius', 0, 80 ),
				'filter_chip_radius'        => array( esc_html__( 'Chip radius', 'mime-simple-events-calendar' ), '--wpse-filter-chip-radius', 0, 80 ),
				'filter_action_radius'      => array( esc_html__( 'Action radius', 'mime-simple-events-calendar' ), '--wpse-filter-action-radius', 0, 80 ),
			) as $id => $control
		) {
			$this->add_responsive_control(
				$id,
				array(
					'label'      => $control[0],
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'rem' ),
					'range'      => array(
						'px' => array(
							'min' => $control[2],
							'max' => $control[3],
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => $control[1] . ': {{SIZE}}{{UNIT}};' ),
				)
			);
		}

		foreach (
			array(
				'filters_border'        => '{{WRAPPER}} .wpse-events-filters',
				'filter_panel_border'   => '{{WRAPPER}} .wpse-events-filter-panel',
				'filter_trigger_border' => '{{WRAPPER}} .wpse-events-filter-toggle',
				'control_border'        => '{{WRAPPER}} .wpse-events-filters select, {{WRAPPER}} .wpse-events-filter-search input',
				'filter_chip_border'    => '{{WRAPPER}} .wpse-events-filter-chip',
				'filter_action_border'  => '{{WRAPPER}} .wpse-events-filter-submit button, {{WRAPPER}} .wpse-events-filter-submit a',
			) as $id => $selector
		) {
			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => $id,
					'selector' => $selector,
				)
			);
		}

		foreach (
			array(
				'filter_trigger_typography' => array( esc_html__( 'Trigger typography', 'mime-simple-events-calendar' ), '{{WRAPPER}} .wpse-events-filter-toggle' ),
				'filter_option_typography'  => array( esc_html__( 'Option typography', 'mime-simple-events-calendar' ), '{{WRAPPER}} .wpse-events-filter-group' ),
				'filter_chip_typography'    => array( esc_html__( 'Chip typography', 'mime-simple-events-calendar' ), '{{WRAPPER}} .wpse-events-filter-chip' ),
				'filter_action_typography'  => array( esc_html__( 'Action typography', 'mime-simple-events-calendar' ), '{{WRAPPER}} .wpse-events-filter-submit' ),
				'filter_status_typography'  => array( esc_html__( 'Status typography', 'mime-simple-events-calendar' ), '{{WRAPPER}} .wpse-events-filter-status, {{WRAPPER}} .wpse-calendar-status' ),
			) as $id => $control
		) {
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => $id,
					'label'    => $control[0],
					'selector' => $control[1],
				)
			);
		}

		$this->end_controls_section();
	}

	/** Create the correct renderer when Elementor reconstructs a widget. */
	abstract protected function default_renderer(): ShortcodeRenderer;
}
