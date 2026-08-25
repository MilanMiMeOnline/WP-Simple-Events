<?php
/**
 * Occurrence projection persistence boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Owns fail-closed complete-generation replacement and health verification.
 */
interface OccurrenceProjectionStore {
	/**
	 * Return or atomically allocate the immutable series UUID.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function series_uid( int $event_id ): ?string;

	/**
	 * Allocate an unpredictable positive generation token.
	 */
	public function new_generation(): int;

	/**
	 * Insert, activate and verify one complete generation before clearing dirty.
	 *
	 * @param int                               $event_id    Canonical event post ID.
	 * @param int                               $generation Generation shared by every row.
	 * @param EventOccurrence[]                 $occurrences Complete bounded occurrence set; may be empty.
	 * @param OccurrenceProjectionCoverage|null $coverage Recurring coverage, or null for one-off state.
	 */
	public function replace(
		int $event_id,
		int $generation,
		array $occurrences,
		?OccurrenceProjectionCoverage $coverage = null
	): bool;

	/**
	 * Remove and verify every derived occurrence row and marker for one event.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function remove( int $event_id ): bool;
}
