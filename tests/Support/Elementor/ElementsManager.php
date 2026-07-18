<?php
/**
 * Minimal Elementor elements-manager double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace Elementor; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- The test double must mirror Elementor's public namespace.

/**
 * Records custom widget categories.
 */
final class Elements_Manager {
	/**
	 * Registered category properties.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $categories = array();

	/**
	 * Record one custom category.
	 *
	 * @param string               $name       Category name.
	 * @param array<string, mixed> $properties Category properties.
	 */
	public function add_category( string $name, array $properties ): void {
		$this->categories[ $name ] = $properties;
	}
}
