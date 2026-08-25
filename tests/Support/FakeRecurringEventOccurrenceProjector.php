<?php
/**
 * In-memory recurring occurrence projector test double.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\RecurringEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Captures complete recurring projection requests.
 */
final class FakeRecurringEventOccurrenceProjector implements RecurringEventOccurrenceProjector {
	/**
	 * Number of projection requests.
	 *
	 * @var int
	 */
	public int $calls = 0;

	/**
	 * Last effective series status.
	 *
	 * @var EventStatus|null
	 */
	public ?EventStatus $status = null;

	/**
	 * Last bounded projection window.
	 *
	 * @var RecurrenceGenerationWindow|null
	 */
	public ?RecurrenceGenerationWindow $window = null;

	/**
	 * Configure the projection result.
	 *
	 * @param bool $result Projection result.
	 */
	public function __construct( private readonly bool $result = true ) {}

	/**
	 * Capture one complete projection request.
	 *
	 * @param int                        $event_id     Canonical event post ID.
	 * @param RecurrenceAggregate        $aggregate    Validated complete aggregate.
	 * @param EventStatus                $series_status Inherited event status.
	 * @param RecurrenceGenerationWindow $window       Explicit bounded projection window.
	 */
	public function project(
		int $event_id,
		RecurrenceAggregate $aggregate,
		EventStatus $series_status,
		RecurrenceGenerationWindow $window
	): bool {
		unset( $event_id, $aggregate );
		++$this->calls;
		$this->status = $series_status;
		$this->window = $window;

		return $this->result;
	}
}
