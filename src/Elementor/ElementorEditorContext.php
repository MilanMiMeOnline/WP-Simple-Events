<?php
/**
 * Native Elementor editor context adapter.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Plugin as ElementorPlugin;

/**
 * Reads Elementor's documented editor-mode service.
 */
final class ElementorEditorContext implements EditorContext {
	/**
	 * Determine whether the current render belongs to the Elementor editor.
	 */
	public function is_editing(): bool {
		return isset( ElementorPlugin::$instance->editor )
			&& true === ElementorPlugin::$instance->editor->is_edit_mode();
	}
}
