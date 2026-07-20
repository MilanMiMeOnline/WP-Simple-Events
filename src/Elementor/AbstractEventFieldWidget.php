<?php
/**
 * Shared Elementor atomic event-field behaviour.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;

/**
 * Resolves one safe event source and renders exactly one named field.
 */
abstract class AbstractEventFieldWidget extends Widget_Base {
	/**
	 * Shared access-aware context resolver.
	 *
	 * @var EventContextResolver
	 */
	protected EventContextResolver $contexts;

	/**
	 * Shared named-field renderer.
	 *
	 * @var EventFieldRenderer
	 */
	protected EventFieldRenderer $fields;

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
	 * Create an atomic widget while preserving Elementor's constructor contract.
	 *
	 * @param mixed                     $data     Elementor widget data.
	 * @param mixed                     $args     Elementor widget arguments.
	 * @param EventContextResolver|null $contexts Shared event context resolver.
	 * @param EventFieldRenderer|null   $fields   Shared field renderer.
	 * @param EditorContext|null        $editor   Editor-mode boundary.
	 * @param PreviewEventOptions|null  $previews Bounded public event choices.
	 */
	public function __construct(
		$data = array(),
		$args = null,
		?EventContextResolver $contexts = null,
		?EventFieldRenderer $fields = null,
		?EditorContext $editor = null,
		?PreviewEventOptions $previews = null
	) { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- The first two parameters mirror Elementor's deliberately untyped API.
		$this->contexts = $contexts ?? AtomicWidgetRuntime::contexts();
		$this->fields   = $fields ?? AtomicWidgetRuntime::fields();
		$this->editor   = $editor ?? new ElementorEditorContext();
		$this->previews = $previews ?? AtomicWidgetRuntime::previews();

		parent::__construct( $data, $args );
	}

	/** Return the dedicated Elementor category. */
	public function get_categories(): array {
		return array( WidgetRegistrar::CATEGORY );
	}

	/** Declare the shared event stylesheet. */
	public function get_style_depends(): array {
		return array( FrontendAssets::STYLE_HANDLE );
	}

	/** Opt into Elementor's optimized DOM. */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/** Register source, field-specific and scoped style controls. */
	final protected function register_controls(): void {
		$this->register_source_controls();
		$this->register_field_controls();
		$this->register_style_controls();
	}

	/** Render a field or an editor-only diagnostic placeholder. */
	final protected function render(): void {
		$settings     = $this->settings();
		$presentation = $this->resolve_event( $settings );
		$output       = null === $presentation ? '' : $this->render_field( $presentation, $settings );

		if ( '' !== $output ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The named shared field renderer owns contextual escaping.
			return;
		}

		if ( $this->editor->is_editing() ) {
			echo '<div class="wpse-elementor-placeholder" role="status">'
				. esc_html__( 'This event field has no public value for the selected or current event.', 'simple-events-by-mime' )
				. '</div>';
		}
	}

	/**
	 * Return normalized display settings as an array boundary.
	 *
	 * @return array<string, mixed>
	 */
	protected function settings(): array {
		$settings = $this->get_settings_for_display();

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Register show/custom label controls for meaningful fields.
	 *
	 * @param string $default_label Translated default-label placeholder.
	 */
	protected function add_label_controls( string $default_label ): void {
		$this->add_control(
			'show_label',
			array(
				'label'        => esc_html__( 'Show label', 'simple-events-by-mime' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'simple-events-by-mime' ),
				'label_off'    => esc_html__( 'Hide', 'simple-events-by-mime' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'label',
			array(
				'label'       => esc_html__( 'Label text', 'simple-events-by-mime' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => $default_label,
				'condition'   => array( 'show_label' => 'yes' ),
			)
		);
	}

	/**
	 * Whether the saved switcher enables the field label.
	 *
	 * @param array<string, mixed> $settings Display settings.
	 */
	protected function show_label( array $settings ): bool {
		return AtomicWidgetSettings::switcher( $settings, 'show_label', true );
	}

	/**
	 * Return the sanitized custom field label.
	 *
	 * @param array<string, mixed> $settings Display settings.
	 */
	protected function label( array $settings ): string {
		return AtomicWidgetSettings::text( $settings['label'] ?? null );
	}

	/** Register controls unique to the field. */
	abstract protected function register_field_controls(): void;

	/**
	 * Render the named field through the shared renderer.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	abstract protected function render_field( EventPresentation $presentation, array $settings ): string;

	/** Return the stable semantic selector styled by Elementor. */
	abstract protected function field_selector(): string;

	/** Whether typography and text color apply to this field. */
	protected function supports_text_style(): bool {
		return true;
	}

	/** Register the bounded explicit-source control. */
	private function register_source_controls(): void {
		$this->start_controls_section(
			'wpse_source',
			array(
				'label' => esc_html__( 'Event source', 'simple-events-by-mime' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'event_id',
			array(
				'label'       => esc_html__( 'Event', 'simple-events-by-mime' ),
				'description' => esc_html__( 'Select a public event for a static page, or leave empty to use the current event.', 'simple-events-by-mime' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->previews->options(),
				'default'     => '',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Resolve explicit selections strictly and empty selections from context.
	 *
	 * @param array<string, mixed> $settings Display settings.
	 */
	private function resolve_event( array $settings ): ?EventPresentation {
		$value = $settings['event_id'] ?? '';

		if ( ( is_string( $value ) || is_int( $value ) ) && '' === trim( (string) $value ) ) {
			return $this->contexts->resolve_current();
		}

		$event_id = AtomicWidgetSettings::event_id( $value );

		return null === $event_id ? null : $this->contexts->resolve_public( $event_id );
	}

	/** Register theme-inheriting, wrapper-scoped visual controls. */
	private function register_style_controls(): void {
		$selector      = '{{WRAPPER}} ' . $this->field_selector();
		$text_selector = $selector . ', ' . $selector . ' a';

		$this->start_controls_section(
			'wpse_style',
			array(
				'label' => esc_html__( 'Style', 'simple-events-by-mime' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		if ( $this->supports_text_style() ) {
			$this->add_control(
				'text_color',
				array(
					'label'     => esc_html__( 'Text color', 'simple-events-by-mime' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $text_selector => 'color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'typography',
					'selector' => $text_selector,
				)
			);
		}

		$this->add_control(
			'spacing',
			array(
				'label'      => esc_html__( 'Bottom spacing', 'simple-events-by-mime' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 200,
					),
				),
				'selectors'  => array( $selector => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}
}
