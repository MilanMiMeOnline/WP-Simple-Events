<?php
/**
 * Minimal Elementor controls-manager double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace Elementor; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- The test double must mirror Elementor's public namespace.

/**
 * Defines the external control constants used by production widgets.
 */
final class Controls_Manager {
	public const TAB_CONTENT = 'content';
	public const TAB_STYLE   = 'style';
	public const SELECT      = 'select';
	public const SELECT2     = 'select2';
	public const NUMBER      = 'number';
	public const SWITCHER    = 'switcher';
	public const COLOR       = 'color';
	public const SLIDER      = 'slider';
}
