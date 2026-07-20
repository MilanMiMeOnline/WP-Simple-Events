<?php
/**
 * Elementor external event-action widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders the configured external event destination. */
final class EventExternalActionWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-external-action';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'External Event Action', 'wp-simple-events' );
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
				'label' => esc_html__( 'External action', 'wp-simple-events' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'link_text',
			array(
				'label'       => esc_html__( 'Override link text', 'wp-simple-events' ),
				'description' => esc_html__( 'Leave empty to use the label saved on the event.', 'wp-simple-events' ),
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
}
