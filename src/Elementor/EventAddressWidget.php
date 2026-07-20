<?php
/**
 * Elementor event address widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders one event postal address. */
final class EventAddressWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-address';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Address', 'simple-events-by-mime' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-google-maps';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'address', 'location' );
	}

	/** Address has no content controls beyond its event source. */
	protected function register_field_controls(): void {
	}

	/**
	 * Render the postal-address field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		unset( $settings );

		return $this->fields->address( $presentation );
	}

	/** Return the address style selector. */
	protected function field_selector(): string {
		return '.wpse-event-address';
	}
}
