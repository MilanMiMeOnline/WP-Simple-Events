<?php
/**
 * Public event-details shortcode.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\EventDetailsRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use WP_Post;

/**
 * Selects a visible event and delegates all presentation to the shared renderer.
 */
final readonly class EventDetailsShortcode implements ShortcodeRenderer {
	/**
	 * Create the shortcode adapter.
	 *
	 * @param EventDetailsRenderer $renderer Shared complete event renderer.
	 * @param FrontendAssets       $assets   Scoped front-end assets.
	 */
	public function __construct(
		private EventDetailsRenderer $renderer = new EventDetailsRenderer(),
		private FrontendAssets $assets = new FrontendAssets()
	) {}

	/**
	 * Register the public shortcode.
	 */
	public function register(): void {
		add_shortcode( 'wpse_event_details', array( $this, 'render' ) );
	}

	/**
	 * Render the queried event or an explicitly selected public event.
	 *
	 * @param array<string, mixed>|string $attributes Raw shortcode attributes.
	 */
	public function render( array|string $attributes = array() ): string {
		$normalized = EventDetailsAttributes::from_shortcode( is_array( $attributes ) ? $attributes : array() );
		$event_id   = $normalized->event_id ?? get_queried_object_id();

		if ( $event_id < 1 || ( $normalized->has_explicit_id && null === $normalized->event_id ) ) {
			return '';
		}

		$event = get_post( $event_id );

		if ( ! $event instanceof WP_Post || EventPostType::POST_TYPE !== $event->post_type ) {
			return '';
		}

		if ( $normalized->has_explicit_id ) {
			if ( 'publish' !== $event->post_status || '' !== $event->post_password ) {
				return '';
			}
		} elseif ( 'publish' !== $event->post_status && ! current_user_can( 'read_post', $event_id ) ) {
			return '';
		}

		$this->assets->enqueue();

		return $this->renderer->render( $event_id );
	}
}
