<?php
/**
 * Elementor event details widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

/**
 * Renders current or explicitly selected event details through native markup.
 */
final class EventDetailsWidget extends AbstractEventWidget {
	/**
	 * Editor-mode boundary.
	 *
	 * @var EditorContext
	 */
	private EditorContext $editor;

	/**
	 * Bounded public preview choices.
	 *
	 * @var PreviewEventOptions
	 */
	private PreviewEventOptions $previews;

	/**
	 * Create the details adapter while preserving Elementor's constructor.
	 *
	 * @param mixed                    $data     Elementor widget data.
	 * @param mixed                    $args     Elementor widget arguments.
	 * @param ShortcodeRenderer|null   $renderer Shared native renderer.
	 * @param EditorContext|null       $editor   Editor-mode boundary.
	 * @param PreviewEventOptions|null $previews Public preview choices.
	 */
	public function __construct(
		$data = array(),
		$args = null,
		?ShortcodeRenderer $renderer = null,
		?EditorContext $editor = null,
		?PreviewEventOptions $previews = null
	) { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- The first two parameters mirror Elementor's deliberately untyped API.
		$this->editor   = $editor ?? new ElementorEditorContext();
		$this->previews = $previews ?? new PreviewEventOptions();

		parent::__construct( $data, $args, $renderer );
	}

	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-details';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Details', 'mime-simple-events-calendar' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-single-post';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'details', 'date', 'venue', 'location' );
	}

	/** Register preview and style controls. */
	protected function register_controls(): void {
		$this->start_controls_section(
			'wpse_content',
			array(
				'label' => esc_html__( 'Event', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'event_id',
			array(
				'label'       => esc_html__( 'Event source', 'mime-simple-events-calendar' ),
				'description' => esc_html__( 'Optional. On an event template, leave empty to use the current event.', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'placeholder' => esc_html__( 'Current event (automatic)', 'mime-simple-events-calendar' ),
				'options'     => $this->previews->options(),
				'default'     => '',
			)
		);
		foreach (
			array(
				'show_title'    => esc_html__( 'Show title', 'mime-simple-events-calendar' ),
				'show_image'    => esc_html__( 'Show image', 'mime-simple-events-calendar' ),
				'show_date'     => esc_html__( 'Show date and time', 'mime-simple-events-calendar' ),
				'show_status'   => esc_html__( 'Show event status', 'mime-simple-events-calendar' ),
				'show_location' => esc_html__( 'Show location details', 'mime-simple-events-calendar' ),
				'show_content'  => esc_html__( 'Show content', 'mime-simple-events-calendar' ),
				'show_action'   => esc_html__( 'Show external action', 'mime-simple-events-calendar' ),
				'show_terms'    => esc_html__( 'Show categories and tags', 'mime-simple-events-calendar' ),
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
			'heading_level',
			array(
				'label'     => esc_html__( 'Title heading level', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'h1',
				'options'   => array(
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				),
				'condition' => array( 'show_title' => 'yes' ),
			)
		);
		foreach (
			array(
				'date_label'       => array( esc_html__( 'Date label', 'mime-simple-events-calendar' ), 'show_date' ),
				'venue_label'      => array( esc_html__( 'Venue label', 'mime-simple-events-calendar' ), 'show_location' ),
				'location_label'   => array( esc_html__( 'Location link text', 'mime-simple-events-calendar' ), 'show_location' ),
				'action_label'     => array( esc_html__( 'External action text', 'mime-simple-events-calendar' ), 'show_action' ),
				'categories_label' => array( esc_html__( 'Categories label', 'mime-simple-events-calendar' ), 'show_terms' ),
				'tags_label'       => array( esc_html__( 'Tags label', 'mime-simple-events-calendar' ), 'show_terms' ),
			) as $id => $definition
		) {
			$this->add_control(
				$id,
				array(
					'label'       => $definition[0],
					'type'        => Controls_Manager::TEXT,
					'placeholder' => esc_html__( 'Use event default', 'mime-simple-events-calendar' ),
					'condition'   => array( $definition[1] => 'yes' ),
				)
			);
		}
		$this->end_controls_section();
		$this->register_style_controls();
	}

	/** Render through the shared shortcode instance with an editor-only fallback. */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$settings = is_array( $settings ) ? $settings : array();
		$output   = $this->renderer->render( WidgetSettings::details( $settings ) );

		if ( '' !== $output ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared native renderer owns contextual escaping.
			return;
		}

		if ( $this->editor->is_editing() ) {
			echo '<div class="wpse-elementor-placeholder" role="status">'
				. esc_html__( 'Select a public event for this preview, or use the widget in an Event template.', 'mime-simple-events-calendar' )
				. '</div>';
		}
	}

	/** Create a renderer when Elementor reconstructs the widget. */
	protected function default_renderer(): ShortcodeRenderer {
		return AtomicWidgetRuntime::details();
	}

	/** Register theme-inheriting visual controls. */
	private function register_style_controls(): void {
		$this->start_controls_section(
			'wpse_style',
			array(
				'label' => esc_html__( 'Event details', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-single-event' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'muted_color',
			array(
				'label'     => esc_html__( 'Secondary text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-single-event' => '--wpse-color-muted: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'border_color',
			array(
				'label'     => esc_html__( 'Border color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-single-event' => '--wpse-color-border: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'spacing',
			array(
				'label'      => esc_html__( 'Spacing', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-single-event' => '--wpse-spacing: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'summary_background_color',
			array(
				'label'     => esc_html__( 'Summary background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-event-summary' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'summary_padding',
			array(
				'label'      => esc_html__( 'Summary padding', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-event-summary' => '--wpse-summary-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'summary_border',
				'selector' => '{{WRAPPER}} .wpse-event-summary',
			)
		);
		$this->add_responsive_control(
			'summary_border_radius',
			array(
				'label'      => esc_html__( 'Summary border radius', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-event-summary' => '--wpse-summary-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'image_ratio',
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
				'selectors' => array( '{{WRAPPER}} .wpse-single-event-image' => '--wpse-single-image-ratio: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'image_border_radius',
			array(
				'label'      => esc_html__( 'Image border radius', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-single-event-image' => '--wpse-single-image-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'action_background_color',
			array(
				'label'     => esc_html__( 'Action background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-event-action-link' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'action_text_color',
			array(
				'label'     => esc_html__( 'Action text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-event-action-link' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'action_padding',
			array(
				'label'      => esc_html__( 'Action padding', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-event-action-link' => '--wpse-action-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'action_border',
				'selector' => '{{WRAPPER}} .wpse-event-action-link',
			)
		);
		$this->add_responsive_control(
			'action_border_radius',
			array(
				'label'      => esc_html__( 'Action border radius', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-event-action-link' => '--wpse-action-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'details_typography',
				'label'    => esc_html__( 'Event details typography', 'mime-simple-events-calendar' ),
				'selector' => '{{WRAPPER}} .wpse-single-event',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'label'    => esc_html__( 'Field label typography', 'mime-simple-events-calendar' ),
				'selector' => '{{WRAPPER}} .wpse-event-label',
			)
		);
		$this->end_controls_section();
	}
}
