<?php
/**
 * WordPress sitemap-provider double for isolated tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- WordPress core test double must use Core's global class name.
/** Minimal abstract shape required by the production provider. */
abstract class WP_Sitemaps_Provider {
	/**
	 * Provider name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Provider object type.
	 *
	 * @var string
	 */
	protected string $object_type = '';

	/**
	 * Return one sitemap URL page.
	 *
	 * @param mixed  $page_num       Page number.
	 * @param string $object_subtype Optional subtype.
	 * @return array<int, array<string, string>>
	 */
	abstract public function get_url_list( $page_num, $object_subtype = '' );

	/**
	 * Return the number of sitemap pages.
	 *
	 * @param string $object_subtype Optional subtype.
	 */
	abstract public function get_max_num_pages( $object_subtype = '' );
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
