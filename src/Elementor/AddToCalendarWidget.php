<?php
/**
 * Elementor add-to-calendar widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarOptions;
use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;

/** Exposes the shared one-event action contract without making Elementor required. */
final class AddToCalendarWidget extends Widget_Base {
	/**
	 * Shared occurrence-aware action renderer.
	 *
	 * @var AddToCalendarRenderer
	 */
	private AddToCalendarRenderer $renderer;

	/**
	 * Editor-only placeholder boundary.
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
	 * Create the widget while preserving Elementor's constructor contract.
	 *
	 * @param mixed                      $data      Elementor widget data.
	 * @param mixed                      $args      Elementor widget arguments.
	 * @param AddToCalendarRenderer|null $renderer Shared action renderer.
	 * @param EditorContext|null         $editor   Editor-mode boundary.
	 * @param PreviewEventOptions|null   $previews Bounded public event choices.
	 */
	public function __construct(
		$data = array(),
		$args = null,
		?AddToCalendarRenderer $renderer = null,
		?EditorContext $editor = null,
		?PreviewEventOptions $previews = null
	) { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- The first two parameters mirror Elementor's deliberately untyped API.
		$this->renderer = $renderer ?? new AddToCalendarRenderer();
		$this->editor   = $editor ?? new ElementorEditorContext();
		$this->previews = $previews ?? new PreviewEventOptions();

		parent::__construct( $data, $args );
	}

	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-add-to-calendar';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Add to Calendar', 'mime-simple-events-calendar' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-calendar';
	}

	/** Return the dedicated Elementor category. */
	public function get_categories(): array {
		return array( WidgetRegistrar::CATEGORY );
	}

	/** Declare the shared component stylesheet. */
	public function get_style_depends(): array {
		return array( FrontendAssets::STYLE_HANDLE );
	}

	/** Opt into Elementor's optimized DOM. */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'calendar', 'ics', 'google', 'outlook', 'download' );
	}

	/** Register bounded source, provider, layout and style controls. */
	protected function register_controls(): void {
		$this->start_controls_section(
			'wpse_content',
			array(
				'label' => esc_html__( 'Calendar action', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'event_id',
			array(
				'label'       => esc_html__( 'Event source', 'mime-simple-events-calendar' ),
				'description' => esc_html__( 'Select a public event for a regular page, or leave empty to use the current event or occurrence.', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'placeholder' => esc_html__( 'Current event (automatic)', 'mime-simple-events-calendar' ),
				'options'     => $this->previews->options(),
				'default'     => '',
			)
		);

		foreach (
			array(
				'provider_ics'     => array( esc_html__( 'Calendar file (ICS)', 'mime-simple-events-calendar' ), 'yes' ),
				'provider_google'  => array( esc_html__( 'Google Calendar', 'mime-simple-events-calendar' ), '' ),
				'provider_outlook' => array( esc_html__( 'Outlook', 'mime-simple-events-calendar' ), '' ),
			) as $id => $control
		) {
			$this->add_control(
				$id,
				array(
					'label'        => $control[0],
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => $control[1],
				)
			);
		}

		$this->add_control(
			'layout',
			array(
				'label'       => esc_html__( 'Multiple-provider layout', 'mime-simple-events-calendar' ),
				'description' => esc_html__( 'A single provider always renders as one direct action.', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'dropdown',
				'options'     => array(
					'dropdown' => esc_html__( 'Dropdown', 'mime-simple-events-calendar' ),
					'list'     => esc_html__( 'Separate actions', 'mime-simple-events-calendar' ),
				),
			)
		);
		$this->add_control(
			'label',
			array(
				'label'       => esc_html__( 'Dropdown or list label', 'mime-simple-events-calendar' ),
				'description' => esc_html__( 'Leave empty to use the translated default.', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::TEXT,
			)
		);
		$this->end_controls_section();
		$this->register_style_controls();
	}

	/** Render the shared component or one editor-only diagnostic. */
	protected function render(): void {
		$settings  = $this->get_settings_for_display();
		$settings  = is_array( $settings ) ? $settings : array();
		$providers = array();

		foreach (
			array(
				'provider_ics'     => array( 'ics', true ),
				'provider_google'  => array( 'google', false ),
				'provider_outlook' => array( 'outlook', false ),
			) as $key => $provider
		) {
			if ( AtomicWidgetSettings::switcher( $settings, $key, $provider[1] ) ) {
				$providers[] = $provider[0];
			}
		}

		$options = AddToCalendarOptions::from_input(
			$providers,
			AtomicWidgetSettings::choice( $settings['layout'] ?? null, array( 'dropdown', 'list' ), 'dropdown' ),
			AtomicWidgetSettings::text( $settings['label'] ?? null )
		);
		$value   = $settings['event_id'] ?? '';

		if ( ( is_string( $value ) || is_int( $value ) ) && '' === trim( (string) $value ) ) {
			$event_id = get_queried_object_id();
			$output   = $event_id > 0 ? $this->renderer->render_current( $event_id, $options ) : '';
		} else {
			$event_id = AtomicWidgetSettings::event_id( $value );
			$output   = null === $event_id ? '' : $this->renderer->render_public( $event_id, $options );
		}

		if ( '' !== $output ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared renderer owns complete contextual escaping.
			return;
		}

		if ( $this->editor->is_editing() ) {
			echo '<div class="wpse-elementor-placeholder" role="status">'
				. esc_html__( 'Select a public event and at least one provider, or use the widget in an Event template.', 'mime-simple-events-calendar' )
				. '</div>';
		}
	}

	/** Register wrapper-scoped visual controls that map to shared CSS properties. */
	private function register_style_controls(): void {
		$this->start_controls_section(
			'wpse_style',
			array(
				'label' => esc_html__( 'Calendar action', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		foreach (
			array(
				'action_background' => array( esc_html__( 'Action background', 'mime-simple-events-calendar' ), '--wpse-calendar-action-background' ),
				'action_text'       => array( esc_html__( 'Action text', 'mime-simple-events-calendar' ), '--wpse-calendar-action-text' ),
				'action_border'     => array( esc_html__( 'Action border', 'mime-simple-events-calendar' ), '--wpse-calendar-action-border' ),
				'menu_background'   => array( esc_html__( 'Menu background', 'mime-simple-events-calendar' ), '--wpse-calendar-menu-background' ),
			) as $id => $control
		) {
			$this->add_control(
				$id,
				array(
					'label'     => $control[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpse-add-to-calendar' => $control[1] . ': {{VALUE}};' ),
				)
			);
		}

		$this->add_responsive_control(
			'action_padding',
			array(
				'label'      => esc_html__( 'Action padding', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-add-to-calendar-summary, {{WRAPPER}} .wpse-add-to-calendar-action' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'action_radius',
			array(
				'label'      => esc_html__( 'Action corner radius', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .wpse-add-to-calendar' => '--wpse-calendar-action-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'action_gap',
			array(
				'label'      => esc_html__( 'Space between actions', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'selectors'  => array( '{{WRAPPER}} .wpse-add-to-calendar' => '--wpse-calendar-action-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'menu_padding',
			array(
				'label'      => esc_html__( 'Menu padding', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-add-to-calendar-actions' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'calendar_action_border',
				'selector' => '{{WRAPPER}} .wpse-add-to-calendar-summary, {{WRAPPER}} .wpse-add-to-calendar-action',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'calendar_action_typography',
				'label'    => esc_html__( 'Typography', 'mime-simple-events-calendar' ),
				'selector' => '{{WRAPPER}} .wpse-add-to-calendar',
			)
		);
		$this->end_controls_section();
	}
}
