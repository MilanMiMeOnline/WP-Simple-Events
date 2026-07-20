<?php
/**
 * Public event assets.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Registers stable, scoped front-end asset handles.
 */
final class FrontendAssets {
	public const STYLE_HANDLE = 'wpse-frontend';

	/**
	 * Register front-end asset discovery.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_and_maybe_enqueue' ) );
	}

	/**
	 * Register the style and enqueue it for statically discoverable event views.
	 */
	public function register_and_maybe_enqueue(): void {
		$this->register_style();

		global $post;

		$contains_shortcode = $post instanceof WP_Post
			&& ( has_shortcode( $post->post_content, 'wpse_events' )
				|| has_shortcode( $post->post_content, 'wpse_event_details' )
				|| has_shortcode( $post->post_content, 'wpse_calendar' ) );

		if ( is_post_type_archive( EventPostType::POST_TYPE )
			|| is_singular( EventPostType::POST_TYPE )
			|| $contains_shortcode ) {
			$this->enqueue();
		}
	}

	/**
	 * Enqueue styles when a dynamic renderer is used.
	 */
	public function enqueue(): void {
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			$this->register_style();
		}

		wp_enqueue_style( self::STYLE_HANDLE );
	}

	/**
	 * Register the component-scoped stylesheet.
	 */
	public function register_style(): void {
		wp_register_style(
			self::STYLE_HANDLE,
			plugin_dir_url( WPSE_PLUGIN_FILE ) . 'assets/src/css/frontend.css',
			array(),
			WPSE_VERSION
		);
	}
}
