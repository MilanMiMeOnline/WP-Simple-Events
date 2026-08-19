<?php
/**
 * Elementor external event-action widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders the configured external event destination. */
final class EventExternalActionWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-external-action';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'External Event Action', 'mime-simple-events-calendar' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-button';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'external', 'button', 'registration' );
	}

	/** Register external-action presentation controls. */
	protected function register_field_controls(): void {
		$this->start_controls_section(
			'wpse_presentation',
			array(
				'label' => esc_html__( 'External action', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'link_text',
			array(
				'label'       => esc_html__( 'Override link text', 'mime-simple-events-calendar' ),
				'description' => esc_html__( 'Leave empty to use the label saved on the event.', 'mime-simple-events-calendar' ),
				'type'        => Controls_Manager::TEXT,
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render the external-action field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		return $this->fields->external_action( $presentation, AtomicWidgetSettings::text( $settings['link_text'] ?? null ) );
	}

	/** Return the action style selector. */
	protected function field_selector(): string {
		return '.wpse-event-action';
	}

	/** Register action-specific visual controls. */
	protected function register_additional_style_controls(): void {
		$this->add_control(
			'background_color',
			array(
				'label'     => esc_html__( 'Background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-event-action-link' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'hover_background_color',
			array(
				'label'     => esc_html__( 'Hover background color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-event-action-link:hover, {{WRAPPER}} .wpse-event-action-link:focus-visible' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'hover_text_color',
			array(
				'label'     => esc_html__( 'Hover text color', 'mime-simple-events-calendar' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .wpse-event-action-link:hover, {{WRAPPER}} .wpse-event-action-link:focus-visible' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'padding',
			array(
				'label'      => esc_html__( 'Padding', 'mime-simple-events-calendar' ),
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
			'border_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'mime-simple-events-calendar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .wpse-event-action-link' => '--wpse-action-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
	}
}
