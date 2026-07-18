<?php
/**
 * Formatted public event date presentation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Carries one escaped-late date label and machine-readable boundaries.
 */
final readonly class EventDatePresentation {
	/**
	 * Store formatted event date values.
	 *
	 * @param string $label     Localized visible date label.
	 * @param string $start_iso Machine-readable local start.
	 * @param string $end_iso   Machine-readable local end.
	 */
	public function __construct(
		public string $label,
		public string $start_iso,
		public string $end_iso
	) {}
}
