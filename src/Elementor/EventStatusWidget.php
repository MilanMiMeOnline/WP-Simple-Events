<?php
/**
 * Elementor event status widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders exceptional event status only. */
final class EventStatusWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-status';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Status', 'wp-simple-events' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-alert';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'status', 'cancelled', 'postponed' );
	}

	/** Status has no content controls beyond its event source. */
	protected function register_field_controls(): void {
	}

	/**
	 * Render the exceptional-status field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		unset( $settings );

		return $this->fields->status( $presentation );
	}

	/** Return the status style selector. */
	protected function field_selector(): string {
		return '.wpse-event-status';
	}
}
