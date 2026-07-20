<?php
/**
 * Elementor event excerpt widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders one event excerpt. */
final class EventExcerptWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-excerpt';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Excerpt', 'simple-events-by-mime' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-text';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'excerpt', 'summary' );
	}

	/** Excerpt has no controls beyond its event source. */
	protected function register_field_controls(): void {
	}

	/**
	 * Render the excerpt field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		unset( $settings );

		return $this->fields->excerpt( $presentation );
	}

	/** Return the excerpt style selector. */
	protected function field_selector(): string {
		return '.wpse-event-excerpt';
	}
}
