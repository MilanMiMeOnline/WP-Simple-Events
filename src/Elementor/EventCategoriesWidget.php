<?php
/**
 * Elementor event categories widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders linked event categories. */
final class EventCategoriesWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-categories';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Categories', 'wp-simple-events' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-folder-o';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'categories', 'taxonomy' );
	}

	/** Register category-label controls. */
	protected function register_field_controls(): void {
		$this->start_controls_section(
			'wpse_presentation',
			array(
				'label' => esc_html__( 'Categories', 'wp-simple-events' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_label_controls( esc_html__( 'Categories:', 'wp-simple-events' ) );
		$this->end_controls_section();
	}

	/**
	 * Render the category field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		return $this->fields->categories( $presentation, $this->show_label( $settings ), $this->label( $settings ) );
	}

	/** Return the category style selector. */
	protected function field_selector(): string {
		return '.wpse-event-categories';
	}
}
