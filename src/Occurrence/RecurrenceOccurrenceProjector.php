<?php
/**
 * Complete recurring event occurrence projection.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Builds one fully reconciled generation and preserves failure health markers.
 */
final class RecurrenceOccurrenceProjector implements RecurringEventOccurrenceProjector {
	/**
	 * Create the recurring projector.
	 *
	 * @param OccurrenceProjectionStore   $store   Projection persistence boundary.
	 * @param RecurrenceOccurrenceBuilder $builder Pure complete-generation builder.
	 */
	public function __construct(
		private readonly OccurrenceProjectionStore $store = new WordPressOccurrenceProjectionStore(),
		private readonly RecurrenceOccurrenceBuilder $builder = new RecurrenceOccurrenceBuilder()
	) {}

	/**
	 * Build and activate one complete bounded recurring projection.
	 *
	 * @param int                        $event_id      Canonical event post ID.
	 * @param RecurrenceAggregate        $aggregate     Validated complete aggregate.
	 * @param EventStatus                $series_status Inherited event status.
	 * @param RecurrenceGenerationWindow $window        Explicit bounded projection window.
	 */
	public function project(
		int $event_id,
		RecurrenceAggregate $aggregate,
		EventStatus $series_status,
		RecurrenceGenerationWindow $window
	): bool {
		if ( $event_id <= 0 || $this->store->series_uid( $event_id ) !== $aggregate->series_uid ) {
			$this->record_health( $event_id, false );

			return false;
		}

		$generation = $this->store->new_generation();

		try {
			$occurrences = $this->builder->build( $event_id, $aggregate, $series_status, $window, $generation );
		} catch ( InvalidArgumentException ) {
			$this->record_health( $event_id, false );

			return false;
		}

		$result = $this->store->replace(
			$event_id,
			$generation,
			$occurrences,
			OccurrenceProjectionCoverage::from_window( $window )
		);
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
		if ( $event_id <= 0 ) {
			return;
		}

		if ( ! $healthy ) {
			update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );
		}
	}
}
