<?php
/**
 * Derived event projection-state lifecycle boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Resets disposable event markers after complete occurrence-table loss.
 */
interface OccurrenceProjectionStateLifecycle {
	/** Remove only plugin-owned derived projection markers. */
	public function reset(): void;
}
