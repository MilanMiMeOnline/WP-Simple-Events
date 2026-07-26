<?php
/**
 * Elementor event featured-image widget.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Controls_Manager;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Renders one event featured image. */
final class EventFeaturedImageWidget extends AbstractEventFieldWidget {
	/** Return the stable widget identifier. */
	public function get_name(): string {
		return 'wpse-event-featured-image';
	}

	/** Return the translated editor title. */
	public function get_title(): string {
		return esc_html__( 'Event Featured Image', 'mime-simple-events-calendar' );
	}

	/** Return the Elementor panel icon. */
	public function get_icon(): string {
		return 'eicon-featured-image';
	}

	/**
	 * Return editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'event', 'image', 'featured', 'poster' );
	}

	/** Register image presentation controls. */
	protected function register_field_controls(): void {
		$this->start_controls_section(
			'wpse_presentation',
			array(
				'label' => esc_html__( 'Image', 'mime-simple-events-calendar' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'image_size',
			array(
				'label'   => esc_html__( 'Image size', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'thumbnail'    => esc_html__( 'Thumbnail', 'mime-simple-events-calendar' ),
					'medium'       => esc_html__( 'Medium', 'mime-simple-events-calendar' ),
					'medium_large' => esc_html__( 'Medium large', 'mime-simple-events-calendar' ),
					'large'        => esc_html__( 'Large', 'mime-simple-events-calendar' ),
					'full'         => esc_html__( 'Full size', 'mime-simple-events-calendar' ),
				),
				'default' => 'large',
			)
		);
		$this->add_control(
			'alt_mode',
			array(
				'label'   => esc_html__( 'Alternative text', 'mime-simple-events-calendar' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'attachment' => esc_html__( 'Use Media Library alt text', 'mime-simple-events-calendar' ),
					'decorative' => esc_html__( 'Decorative (empty alt)', 'mime-simple-events-calendar' ),
				),
				'default' => 'attachment',
			)
		);
		$this->add_control(
			'link',
			array(
				'label'        => esc_html__( 'Link to event', 'mime-simple-events-calendar' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render the featured-image field.
	 *
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $settings     Display settings.
	 */
	protected function render_field( EventPresentation $presentation, array $settings ): string {
		return $this->fields->featured_image(
			$presentation,
			AtomicWidgetSettings::choice( $settings['image_size'] ?? null, array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), 'large' ),
			AtomicWidgetSettings::switcher( $settings, 'link', false ),
			AtomicWidgetSettings::choice( $settings['alt_mode'] ?? null, array( 'attachment', 'decorative' ), 'attachment' )
		);
	}

	/** Return the image style selector. */
	protected function field_selector(): string {
		return '.wpse-single-event-image';
	}

	/** Image spacing is styled without text controls. */
	protected function supports_text_style(): bool {
		return false;
	}
}
