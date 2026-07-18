<?php
/**
 * Event date-index batch outcome.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Maintenance;

/**
 * Carries bounded maintenance counters and continuation state.
 */
final readonly class EventDateIndexBatchResult {
	/**
	 * Store one batch outcome.
	 *
	 * @param int  $processed Number of event records inspected.
	 * @param int  $changed   Number of repaired or cleared index pairs.
	 * @param int  $skipped   Number of invalid canonical records left untouched.
	 * @param int  $failed    Number of persistence failures safe to retry.
	 * @param bool $has_more  Whether another bounded page may exist.
	 * @param int  $next_page Next page number.
	 */
	public function __construct(
		public int $processed,
		public int $changed,
		public int $skipped,
		public int $failed,
		public bool $has_more,
		public int $next_page
	) {}
}
