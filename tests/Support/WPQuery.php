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
			return 'wpse_event' === $post_type;
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
