<?php
/**
 * One-off event occurrence projector.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Gives existing one-off events the same future read model as recurring events.
 */
final class OneOffOccurrenceProjector implements EventOccurrenceProjector {
	/**
	 * Create the projector.
	 *
	 * @param OccurrenceProjectionStore $store Projection persistence boundary.
	 */
	public function __construct(
		private readonly OccurrenceProjectionStore $store = new WordPressOccurrenceProjectionStore()
	) {}

	/**
	 * Replace one complete one-off generation without changing public rendering.
	 *
	 * @param int                 $event_id   Canonical event post ID.
	 * @param EventDateRange|null $date_range Validated range or incomplete draft.
	 * @param EventStatus         $status     Effective event status.
	 */
	public function project_one_off( int $event_id, ?EventDateRange $date_range, EventStatus $status ): bool {
		if ( $event_id <= 0 ) {
			return false;
		}

		if ( null === $date_range ) {
			$result = $this->store->remove( $event_id );
			$this->record_health( $event_id, $result );

			return $result;
		}

		$series_uid = $this->store->series_uid( $event_id );

		if ( null === $series_uid ) {
			$this->record_health( $event_id, false );

			return false;
		}

		$generation = $this->store->new_generation();

		try {
			$occurrence = new EventOccurrence(
				$event_id,
				OccurrenceIdentity::from( $series_uid, 'one-off' ),
				$generation,
				0,
				OccurrenceSource::ONE_OFF,
				$date_range,
				$status
			);
		} catch ( InvalidArgumentException ) {
			$this->record_health( $event_id, false );

			return false;
		}

		$result = $this->store->replace( $event_id, $generation, array( $occurrence ) );
		$this->record_health( $event_id, $result );

		return $result;
	}

	/**
	 * Add a canonical failure marker; the store alone clears it after verification.
	 *
	 * @param int  $event_id Canonical event post ID.
	 * @param bool $healthy  Whether projection completed.
	 */
	private function record_health( int $event_id, bool $healthy ): void {
		if ( ! $healthy ) {
			update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );
		}
	}
}
