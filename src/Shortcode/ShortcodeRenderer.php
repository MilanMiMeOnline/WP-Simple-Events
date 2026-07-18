<?php
/**
 * Shared shortcode rendering boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

/**
 * Allows optional presentation adapters to reuse native shortcode rendering.
 */
interface ShortcodeRenderer {
	/**
	 * Render allowlisted attributes through the native presentation contract.
	 *
	 * @param array<string, mixed>|string $attributes Raw render attributes.
	 */
	public function render( array|string $attributes = array() ): string;
}
