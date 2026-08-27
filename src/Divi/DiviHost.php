<?php
/**
 * Divi host discovery boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

/**
 * Reports whether the documented Divi 5 module host is available.
 */
interface DiviHost {
	/** Determine whether the required Divi 5 module APIs are loaded. */
	public function is_loaded(): bool;

	/** Return the detected Divi product version. */
	public function version(): ?string;
}
