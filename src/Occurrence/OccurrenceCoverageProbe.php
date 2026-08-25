<?php
/**
 * Occurrence coverage probe boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Detects whether an established public event still lacks a healthy projection.
 */
interface OccurrenceCoverageProbe {
	/**
	 * Return true when at least one public event requires occurrence repair.
	 */
	public function has_public_gap(): bool;
}
