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
use MiMe\WPSimpleEvents\Shortcode\EventDetailsShortcode;
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
		return esc_html__( 'Event Details', 'wp-simple-events' );
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
				'label' => esc_html__( 'Event', 'wp-simple-events' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'event_id',
			array(
				'label'       => esc_html__( 'Preview event', 'wp-simple-events' ),
				'description' => esc_html__( 'Optional. On an event template, leave empty to use the current event.', 'wp-simple-events' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->previews->options(),
				'default'     => '',
			)
		);
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
				. esc_html__( 'Select a public event for this preview, or use the widget in an Event template.', 'wp-simple-events' )
				. '</div>';
		}
	}

	/** Create a renderer when Elementor reconstructs the widget. */
	protected function default_renderer(): ShortcodeRenderer {
		return new EventDetailsShortcode();
	}

	/** Register theme-inheriting visual controls. */
	private function register_style_controls(): void {
		$this->start_controls_section(
			'wpse_style',
			array(
				'label' => esc_html__( 'Event details', 'wp-simple-events' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Text color', 'wp-simple-events' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-single-event' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'muted_color',
			array(
				'label'     => esc_html__( 'Secondary text color', 'wp-simple-events' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-single-event' => '--wpse-color-muted: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'border_color',
			array(
				'label'     => esc_html__( 'Border color', 'wp-simple-events' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-single-event' => '--wpse-color-border: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'summary_border',
				'selector' => '{{WRAPPER}} .wpse-event-summary',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'details_typography',
				'selector' => '{{WRAPPER}} .wpse-single-event',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'selector' => '{{WRAPPER}} .wpse-event-label',
			)
		);
		$this->end_controls_section();
	}
}
