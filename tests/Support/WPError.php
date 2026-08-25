<?php
/**
 * Minimal WP_Error runtime double for isolated tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Error' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Exact WordPress runtime double.
	/**
	 * Supplies the error code and message consumed by tested services.
	 */
	final class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private string $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Optional error data.
		 *
		 * @var mixed
		 */
		private mixed $data;

		/**
		 * Create one deterministic error.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Optional error data.
		 */
		public function __construct( string $code = '', string $message = '', mixed $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Return the primary error code.
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Return the primary error message.
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/** Return optional primary error data. */
		public function get_error_data(): mixed {
			return $this->data;
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
