<?php
/**
 * Bounded occurrence index batch result.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Immutable migration accounting without event details or personal data.
 */
final readonly class OccurrenceIndexBatchResult {
	/**
	 * Store bounded migration counters.
	 *
	 * @param int  $processed       Events inspected in this batch.
	 * @param int  $indexed         Events successfully projected.
	 * @param int  $skipped_invalid Invalid canonical events isolated for repair.
	 * @param int  $failed          Events that could not be projected.
	 * @param bool $has_more        Whether a full batch requires continuation.
	 */
	public function __construct(
		public int $processed,
		public int $indexed,
		public int $skipped_invalid,
		public int $failed,
		public bool $has_more
	) {}
}
