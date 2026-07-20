<?php
/**
 * Elementor event venue widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders one named event venue. */
final class EventVenueWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-venue';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Venue', 'simple-events-by-mime' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-map-pin';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'venue', 'location' );
	}

	/** Register venue-label controls. */
	protected function register_field_controls(): void {
		$this->start_controls_section(
			'wpse_presentation',
			array(
				'label' => esc_html__( 'Venue', 'simple-events-by-mime' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_label_controls( esc_html__( 'Location:', 'simple-events-by-mime' ) );
		$this->end_controls_section();
	}

	/**
	 * Render the venue field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		return $this->fields->venue( $presentation, $this->show_label( $settings ), $this->label( $settings ) );
	}

	/** Return the venue style selector. */
	protected function field_selector(): string {
		return '.wpse-event-venue';
	}
}
