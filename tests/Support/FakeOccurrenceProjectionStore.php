<?php
/**
 * In-memory occurrence projection store test double.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionStore;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionCoverage;

/**
 * Captures complete occurrence generations without a WordPress database.
 */
final class FakeOccurrenceProjectionStore implements OccurrenceProjectionStore {
	/**
	 * Last complete occurrence set written by the projector.
	 *
	 * @var EventOccurrence[]
	 */
	public array $occurrences = array();

	/**
	 * Event IDs removed from the projection.
	 *
	 * @var int[]
	 */
	public array $removed_event_ids = array();

	/**
	 * Last recurring projection coverage, or null for a one-off write.
	 *
	 * @var OccurrenceProjectionCoverage|null
	 */
	public ?OccurrenceProjectionCoverage $coverage = null;

	/**
	 * Configure deterministic identity, generation and persistence result.
	 *
	 * @param string $uid          Series UUID.
	 * @param int    $generation   Generation token.
	 * @param bool   $write_result Replace/remove result.
	 */
	public function __construct(
		private readonly string $uid = 'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad',
		private readonly int $generation = 73,
		private readonly bool $write_result = true
	) {}

	/**
	 * Return the configured series UID.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function series_uid( int $event_id ): ?string {
		return $event_id > 0 ? $this->uid : null;
	}

	/**
	 * Return the configured generation.
	 */
	public function new_generation(): int {
		return $this->generation;
	}

	/**
	 * Capture one complete generation.
	 *
	 * @param int                               $event_id    Event post ID.
	 * @param int                               $generation Generation token.
	 * @param EventOccurrence[]                 $occurrences Complete occurrence set.
	 * @param OccurrenceProjectionCoverage|null $coverage Recurring coverage.
	 */
	public function replace(
		int $event_id,
		int $generation,
		array $occurrences,
		?OccurrenceProjectionCoverage $coverage = null
	): bool {
		unset( $generation );
		$this->occurrences = $occurrences;
		$this->coverage    = $coverage;

		if ( $this->write_result ) {
			delete_post_meta( $event_id, EventMeta::INDEX_DIRTY );
		}

		return $this->write_result;
	}

	/**
	 * Record projection removal.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function remove( int $event_id ): bool {
		$this->removed_event_ids[] = $event_id;

		if ( $this->write_result ) {
			delete_post_meta( $event_id, EventMeta::INDEX_DIRTY );
		}

		return $this->write_result;
	}
}
