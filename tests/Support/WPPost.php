<?php
/**
 * Minimal WP_Post runtime double for isolated tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Post' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Exact WordPress runtime double.
	/**
	 * Supplies only the public post fields consumed by tested services.
	 */
	final class WP_Post {
		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public int $ID;

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public string $post_type;

		/**
		 * Public URL slug.
		 *
		 * @var string
		 */
		public string $post_name;

		/**
		 * Publication status.
		 *
		 * @var string
		 */
		public string $post_status;

		/**
		 * Optional post password.
		 *
		 * @var string
		 */
		public string $post_password;

		/**
		 * Post title.
		 *
		 * @var string
		 */
		public string $post_title;

		/**
		 * Post excerpt.
		 *
		 * @var string
		 */
		public string $post_excerpt;

		/**
		 * Post content.
		 *
		 * @var string
		 */
		public string $post_content;

		/**
		 * Construct one deterministic post object.
		 *
		 * @param array<string, int|string> $data Post field overrides.
		 */
		public function __construct( array $data = array() ) {
			$this->ID            = isset( $data['ID'] ) ? (int) $data['ID'] : 0;
			$this->post_type     = isset( $data['post_type'] ) ? (string) $data['post_type'] : 'post';
			$this->post_name     = isset( $data['post_name'] ) ? (string) $data['post_name'] : '';
			$this->post_status   = isset( $data['post_status'] ) ? (string) $data['post_status'] : 'draft';
			$this->post_password = isset( $data['post_password'] ) ? (string) $data['post_password'] : '';
			$this->post_title    = isset( $data['post_title'] ) ? (string) $data['post_title'] : '';
			$this->post_excerpt  = isset( $data['post_excerpt'] ) ? (string) $data['post_excerpt'] : '';
			$this->post_content  = isset( $data['post_content'] ) ? (string) $data['post_content'] : '';
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
