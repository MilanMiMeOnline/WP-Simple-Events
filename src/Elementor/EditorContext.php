<?php
/**
 * Elementor editor context boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

/**
 * Distinguishes editor previews from public rendering.
 */
interface EditorContext {
	/**
	 * Determine whether Elementor is currently rendering its editor preview.
	 */
	public function is_editing(): bool;
}
