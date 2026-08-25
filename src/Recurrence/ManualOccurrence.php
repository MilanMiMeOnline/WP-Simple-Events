<?php
/**
 * Manual recurrence occurrence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;

/**
 * Adds one immutable occurrence outside the active generated schedule.
 */
final readonly class ManualOccurrence {
	/**
	 * Create one validated manual occurrence.
	 *
	 * New additions use `manual:{UUID}`. A generated identity is accepted only so
	 * broad-edit reconciliation can detach a modified occurrence without changing
	 * its stable public identity.
	 *
	 * @param string         $recurrence_id Canonical manual or retained generated identity.
	 * @param EventDateRange $date_range    Effective occurrence date range.
	 * @param EventStatus    $status        Effective occurrence status.
	 * @throws InvalidArgumentException When the identity is not canonical manual form.
	 */
	public function __construct(
		public string $recurrence_id,
		public EventDateRange $date_range,
		public EventStatus $status = EventStatus::SCHEDULED
	) {
		if ( 'one-off' === $this->recurrence_id || ! OccurrenceIdentity::valid_recurrence_id( $this->recurrence_id ) ) {
			throw new InvalidArgumentException( 'A manual occurrence requires a canonical manual or generated identity.' );
		}
	}
}
