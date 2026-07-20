<?php
/**
 * Elementor event location-link widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders the event route/location action. */
final class EventLocationLinkWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-location-link';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Location Link', 'wp-simple-events' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-link';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'location', 'route', 'link' );
	}

	/** Register location-link presentation controls. */
	protected function register_field_controls(): void {
		$this->start_controls_section(
			'wpse_presentation',
			array(
				'label' => esc_html__( 'Location link', 'wp-simple-events' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'link_text',
			array(
				'label'       => esc_html__( 'Link text', 'wp-simple-events' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'View location', 'wp-simple-events' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render the location-link field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		return $this->fields->location_action( $presentation, AtomicWidgetSettings::text( $settings['link_text'] ?? null ) );
	}

	/** Return the location-link style selector. */
	protected function field_selector(): string {
		return '.wpse-event-location-link';
	}
}
