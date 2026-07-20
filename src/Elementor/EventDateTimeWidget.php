<?php
/**
 * Elementor event date/time widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders the shared localized event date range. */
final class EventDateTimeWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-date-time';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Date & Time', 'simple-events-by-mime' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-calendar';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'date', 'time', 'timezone' );
	}

	/** Register date-label controls. */
	protected function register_field_controls(): void {
		$this->start_controls_section(
			'wpse_presentation',
			array(
				'label' => esc_html__( 'Date and time', 'simple-events-by-mime' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_label_controls( esc_html__( 'Date and time:', 'simple-events-by-mime' ) );
		$this->end_controls_section();
	}

	/**
	 * Render the date/time field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		return $this->fields->date_time( $presentation, $this->show_label( $settings ), $this->label( $settings ) );
	}

	/** Return the date style selector. */
	protected function field_selector(): string {
		return '.wpse-event-date';
	}
}
