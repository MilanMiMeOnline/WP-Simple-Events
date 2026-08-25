<?php
/**
 * Deterministic occurrence repair test double.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairService;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairStatus;

/**
 * Returns configured results while recording repaired event IDs.
 */
final class FakeOccurrenceIndexRepairer implements OccurrenceIndexRepairService {
	/**
	 * Recorded event IDs.
	 *
	 * @var list<int>
	 */
	public array $event_ids = array();

	/**
	 * Create the configured repairer.
	 *
	 * @param array<int, OccurrenceIndexRepairStatus> $results Result by event ID.
	 */
	public function __construct( private readonly array $results = array() ) {}

	/**
	 * Record and return one configured result.
	 *
	 * @param int    $event_id    Canonical event post ID.
	 * @param string $post_status WordPress publication status.
	 */
	public function repair( int $event_id, string $post_status ): OccurrenceIndexRepairStatus {
		unset( $post_status );
		$this->event_ids[] = $event_id;

		return $this->results[ $event_id ] ?? OccurrenceIndexRepairStatus::INDEXED;
	}
}
