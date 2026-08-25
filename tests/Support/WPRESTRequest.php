<?php
/**
 * Minimal WP_REST_Request runtime double for isolated tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_REST_Request' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Exact WordPress runtime double.
	/** Supplies only named parameters consumed by tested REST controllers. */
	final class WP_REST_Request {
		/**
		 * Request parameters.
		 *
		 * @var array<string, mixed>
		 */
		private array $parameters = array();

		/**
		 * Store one request parameter.
		 *
		 * @param string $key   Parameter name.
		 * @param mixed  $value Parameter value.
		 */
		public function set_param( string $key, mixed $value ): void {
			$this->parameters[ $key ] = $value;
		}

		/**
		 * Return one request parameter.
		 *
		 * @param string $key Parameter name.
		 */
		public function get_param( string $key ): mixed {
			return $this->parameters[ $key ] ?? null;
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
