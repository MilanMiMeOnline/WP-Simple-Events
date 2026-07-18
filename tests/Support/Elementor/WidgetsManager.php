<?php
/**
 * Minimal Elementor widget-manager double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace Elementor; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- The test double must mirror Elementor's public namespace.

/**
 * Records widgets sent through the modern registration method.
 */
final class Widgets_Manager {
	/**
	 * Registered widget instances.
	 *
	 * @var list<Widget_Base>
	 */
	public array $registered = array();

	/**
	 * Record one registered widget.
	 *
	 * @param Widget_Base $widget Widget instance.
	 */
	public function register( Widget_Base $widget ): bool {
		$this->registered[] = $widget;

		return true;
	}
}
