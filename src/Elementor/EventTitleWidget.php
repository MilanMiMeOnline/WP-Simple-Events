<?php
/**
 * Elementor event title widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders one event title. */
final class EventTitleWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-title';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Title', 'simple-events-by-mime' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-post-title';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'title', 'heading' );
	}

	/** Register title presentation controls. */
	protected function register_field_controls(): void {
		$this->start_controls_section(
			'wpse_presentation',
			array(
				'label' => esc_html__( 'Title', 'simple-events-by-mime' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'heading',
			array(
				'label'   => esc_html__( 'HTML tag', 'simple-events-by-mime' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array_combine( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ) ),
				'default' => 'h2',
			)
		);
		$this->add_control(
			'link',
			array(
				'label'        => esc_html__( 'Link to event', 'simple-events-by-mime' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render the title field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		return $this->fields->title(
			$presentation,
			AtomicWidgetSettings::choice( $settings['heading'] ?? null, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h2' ),
			'',
			AtomicWidgetSettings::switcher( $settings, 'link', false )
		);
	}

	/** Return the title style selector. */
	protected function field_selector(): string {
		return '.wpse-single-event-title';
	}
}
