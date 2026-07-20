<?php
/**
 * Elementor event content widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders event content through the shared recursion-safe pipeline. */
final class EventContentWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-content';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Content', 'wp-simple-events' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-post-content';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'content', 'description' );
	}

	/** Content has no controls beyond its event source. */
	protected function register_field_controls(): void {
	}

	/**
	 * Render the content field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		unset( $settings );

		return $this->fields->content( $presentation );
	}

	/** Return the content style selector. */
	protected function field_selector(): string {
		return '.wpse-single-event-content';
	}
}
