<?php
/**
 * Minimal WP_Term runtime double for isolated tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Term' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Exact WordPress runtime double.
	/**
	 * Supplies only the public term fields consumed by tested services.
	 */
	final class WP_Term {
		/**
		 * Term ID.
		 *
		 * @var int
		 */
		public int $term_id;

		/**
		 * Public term name.
		 *
		 * @var string
		 */
		public string $name;

		/**
		 * Public term slug.
		 *
		 * @var string
		 */
		public string $slug;

		/**
		 * Taxonomy name.
		 *
		 * @var string
		 */
		public string $taxonomy;

		/**
		 * Construct one deterministic term object.
		 *
		 * @param array{term_id?: int, name?: string, slug?: string, taxonomy?: string} $data Term field overrides.
		 */
		public function __construct( array $data = array() ) {
			$this->term_id  = $data['term_id'] ?? 0;
			$this->name     = $data['name'] ?? '';
			$this->slug     = $data['slug'] ?? '';
			$this->taxonomy = $data['taxonomy'] ?? '';
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
