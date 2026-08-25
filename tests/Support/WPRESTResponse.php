<?php
/**
 * Minimal WP_REST_Response runtime double for isolated tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_REST_Response' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Exact WordPress runtime double.
	/** Supplies response data and status consumed by tested REST controllers. */
	final class WP_REST_Response {
		/**
		 * Response headers keyed by their original field name.
		 *
		 * @var array<string, string>
		 */
		private array $headers = array();

		/**
		 * Create one deterministic response.
		 *
		 * @param mixed $data   Response data.
		 * @param int   $status HTTP status.
		 */
		public function __construct(
			private mixed $data = null,
			private int $status = 200
		) {}

		/** Return response data. */
		public function get_data(): mixed {
			return $this->data;
		}

		/** Return response status. */
		public function get_status(): int {
			return $this->status;
		}

		/**
		 * Store one response header.
		 *
		 * @param string $key   Header field name.
		 * @param string $value Header value.
		 */
		public function header( string $key, string $value ): void {
			$this->headers[ $key ] = $value;
		}

		/**
		 * Return all stored response headers.
		 *
		 * @return array<string, string>
		 */
		public function get_headers(): array {
			return $this->headers;
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
