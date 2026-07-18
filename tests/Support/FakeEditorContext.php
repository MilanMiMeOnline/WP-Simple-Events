<?php
/**
 * Elementor editor context double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Elementor\EditorContext;

/**
 * Returns a deterministic editor-mode decision.
 */
final readonly class FakeEditorContext implements EditorContext {
	/**
	 * Create a deterministic editor state.
	 *
	 * @param bool $editing Editor-mode decision.
	 */
	public function __construct( private bool $editing ) {}

	/** Return configured editor-mode decision. */
	public function is_editing(): bool {
		return $this->editing;
	}
}
