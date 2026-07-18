<?php
/**
 * Minimal Elementor border-control double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace Elementor; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- The test double must mirror Elementor's public namespace.

/** Elementor border group double. */
final class Group_Control_Border {
	/** Return the external group-control identifier. */
	public static function get_type(): string {
		return 'border';
	}
}
