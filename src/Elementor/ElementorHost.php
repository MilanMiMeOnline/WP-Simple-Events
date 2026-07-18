<?php
/**
 * Elementor host discovery boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

/**
 * Supplies the optional host's lifecycle and version.
 */
interface ElementorHost {
	/** Determine whether Elementor has finished loading. */
	public function is_loaded(): bool;

	/** Return the installed Elementor version, when available. */
	public function version(): ?string;
}
