<?php
/**
 * Inactive occurrence-generation cleanup boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Removes only bounded, old projection rows that are no longer active.
 */
interface OccurrenceGenerationCleaner {
	/**
	 * Remove one bounded batch created at or before the supplied UTC timestamp.
	 *
	 * @param int $cutoff_utc Oldest permitted creation timestamp, inclusive.
	 * @param int $limit      Maximum rows to remove.
	 * @return int|null Removed row count, or null when cleanup could not complete.
	 */
	public function clean_before( int $cutoff_utc, int $limit ): ?int;
}
