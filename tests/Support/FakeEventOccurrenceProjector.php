<?php
/**
 * In-memory event occurrence projector test double.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrenceProjector;

/**
 * Records canonical persistence handoff to the occurrence layer.
 */
final class FakeEventOccurrenceProjector implements EventOccurrenceProjector {
	/**
	 * Recorded projection arguments.
	 *
	 * @var array{event_id: int, date_range: EventDateRange|null, status: EventStatus}|null
	 */
	public ?array $projection = null;

	/**
	 * Configure the projection result.
	 *
	 * @param bool $result Result returned after capture.
	 */
	public function __construct( private readonly bool $result = true ) {}

	/**
	 * Capture one projection request.
	 *
	 * @param int                 $event_id   Canonical event post ID.
	 * @param EventDateRange|null $date_range Validated range or incomplete draft.
	 * @param EventStatus         $status     Effective event status.
	 */
	public function project_one_off( int $event_id, ?EventDateRange $date_range, EventStatus $status ): bool {
		$this->projection = array(
			'event_id'   => $event_id,
			'date_range' => $date_range,
			'status'     => $status,
		);

		return $this->result;
	}
}
