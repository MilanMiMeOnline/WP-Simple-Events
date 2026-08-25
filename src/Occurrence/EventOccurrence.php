<?php
/**
 * Projected event occurrence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Immutable values required by the chronological occurrence projection.
 */
final readonly class EventOccurrence {
	/**
	 * Create one validated occurrence row.
	 *
	 * @param int                $event_id    Canonical event post ID.
	 * @param OccurrenceIdentity $identity    Immutable occurrence identity.
	 * @param int                $generation  Positive projection generation.
	 * @param int                $segment_id  Non-negative schedule segment ID.
	 * @param OccurrenceSource   $source      How the occurrence entered the projection.
	 * @param EventDateRange     $date_range  Effective occurrence date range.
	 * @param EventStatus        $status      Effective occurrence status.
	 * @throws InvalidArgumentException When identifiers are outside their supported range.
	 */
	public function __construct(
		public int $event_id,
		public OccurrenceIdentity $identity,
		public int $generation,
		public int $segment_id,
		public OccurrenceSource $source,
		public EventDateRange $date_range,
		public EventStatus $status
	) {
		if ( $this->event_id <= 0 ) {
			throw new InvalidArgumentException( 'An occurrence requires a positive event ID.' );
		}

		if ( $this->generation <= 0 ) {
			throw new InvalidArgumentException( 'An occurrence generation must be positive.' );
		}

		if ( $this->segment_id < 0 ) {
			throw new InvalidArgumentException( 'An occurrence segment ID cannot be negative.' );
		}
	}

	/**
	 * Return a strict row ready for a storage adapter.
	 *
	 * @return array<string, int|string>
	 */
	public function projection_row(): array {
		return array(
			'event_id'      => $this->event_id,
			'public_key'    => $this->identity->public_key(),
			'recurrence_id' => $this->identity->recurrence_id(),
			'generation'    => $this->generation,
			'segment_id'    => $this->segment_id,
			'source'        => $this->source->value,
			'start_local'   => $this->date_range->start_local(),
			'end_local'     => $this->date_range->end_local(),
			'start_utc'     => $this->date_range->start_utc(),
			'end_utc'       => $this->date_range->end_utc(),
			'timezone'      => $this->date_range->timezone(),
			'all_day'       => $this->date_range->all_day() ? 1 : 0,
			'event_status'  => $this->status->value,
		);
	}
}
