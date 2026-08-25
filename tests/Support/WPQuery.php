<?php
/**
 * Minimal WP_Query runtime double for isolated tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Query' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Exact WordPress runtime double.
	/**
	 * Supplies only the query state consumed by the archive adapter.
	 */
	final class WP_Query {
		/**
		 * Whether the query has been converted to a 404.
		 *
		 * @var bool
		 */
		public bool $is_404 = false;

		/**
		 * Public result posts.
		 *
		 * @var array<int, mixed>
		 */
		public array $posts = array();

		/**
		 * Public total page count.
		 *
		 * @var int
		 */
		public int $max_num_pages = 0;

		/**
		 * Exact public result count.
		 *
		 * @var int
		 */
		public int $found_posts = 0;

		/**
		 * Stored query variables.
		 *
		 * @var array<string, mixed>
		 */
		private array $variables;

		/**
		 * Create one deterministic main archive query.
		 *
		 * @param array<string, mixed> $variables Initial query variables.
		 */
		public function __construct( array $variables = array() ) {
			$this->variables = $variables;
		}

		/**
		 * This double always represents the main query.
		 */
		public function is_main_query(): bool {
			return true;
		}

		/**
		 * Match only the plugin event archive.
		 *
		 * @param string $post_type Requested post type.
		 */
		public function is_post_type_archive( string $post_type = '' ): bool {
			return 'taxonomy' !== $this->get( 'wpse_test_request' )
				&& 'unrelated' !== $this->get( 'wpse_test_request' )
				&& 'wpse_event' === $post_type;
		}

		/**
		 * Match a deterministic taxonomy archive when requested by a test.
		 *
		 * @param string|string[] $taxonomies Requested taxonomies.
		 */
		public function is_tax( string|array $taxonomies = '' ): bool {
			if ( 'taxonomy' !== $this->get( 'wpse_test_request' ) ) {
				return false;
			}

			$taxonomy = $this->get( 'taxonomy' );
			$allowed  = is_array( $taxonomies ) ? $taxonomies : array( $taxonomies );

			return is_string( $taxonomy ) && in_array( $taxonomy, $allowed, true );
		}

		/**
		 * Match a deterministic singular post-type request.
		 *
		 * @param string|string[] $post_types Requested post types.
		 */
		public function is_singular( string|array $post_types = '' ): bool {
			$allowed = is_array( $post_types ) ? $post_types : array( $post_types );

			return 'singular' === $this->get( 'wpse_test_request' )
				&& in_array( $this->get( 'post_type' ), $allowed, true );
		}

		/** Return the deterministic queried post ID. */
		public function get_queried_object_id(): int {
			$post_id = $this->get( 'p' );

			return is_int( $post_id ) ? $post_id : 0;
		}

		/** Convert the deterministic query to a 404. */
		public function set_404(): void {
			$this->is_404 = true;
		}

		/**
		 * Read a query variable.
		 *
		 * @param string $key Query variable name.
		 */
		public function get( string $key ): mixed {
			return $this->variables[ $key ] ?? '';
		}

		/**
		 * Set a query variable.
		 *
		 * @param string $key   Query variable name.
		 * @param mixed  $value Query variable value.
		 */
		public function set( string $key, mixed $value ): void {
			$this->variables[ $key ] = $value;
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
