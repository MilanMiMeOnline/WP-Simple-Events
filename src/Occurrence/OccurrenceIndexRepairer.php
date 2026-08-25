<?php
/**
 * Type-aware occurrence index repair.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use RuntimeException;

/**
 * Chooses recurring or one-off projection only after canonical aggregate decode.
 */
final readonly class OccurrenceIndexRepairer implements OccurrenceIndexRepairService {
	/**
	 * Create the canonical repair service.
	 *
	 * @param RecurrenceAggregateStore          $aggregates Recurrence source of truth.
	 * @param RecurringEventOccurrenceProjector $recurring  Recurring projection writer.
	 * @param OneOffOccurrenceIndexRepairer     $one_off    One-off repair service.
	 * @param OccurrenceRepairWindowFactory     $windows    Production repair window.
	 * @param EventMetaSanitizer                $sanitizer Event metadata sanitizer.
	 */
	public function __construct(
		private RecurrenceAggregateStore $aggregates = new WordPressRecurrenceAggregateStore(),
		private RecurringEventOccurrenceProjector $recurring = new RecurrenceOccurrenceProjector(),
		private OneOffOccurrenceIndexRepairer $one_off = new OneOffOccurrenceIndexRepairer(),
		private OccurrenceRepairWindowFactory $windows = new OccurrenceRepairWindowFactory(),
		private EventMetaSanitizer $sanitizer = new EventMetaSanitizer()
	) {}

	/**
	 * Repair one event without ever treating corrupt recurrence as one-off.
	 *
	 * @param int    $event_id    Canonical event post ID.
	 * @param string $post_status WordPress publication status.
	 */
	public function repair( int $event_id, string $post_status ): OccurrenceIndexRepairStatus {
		if ( $event_id <= 0 ) {
			return OccurrenceIndexRepairStatus::FAILED;
		}

		try {
			$aggregate = $this->aggregates->load( $event_id );
		} catch ( InvalidArgumentException ) {
			update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

			return OccurrenceIndexRepairStatus::INVALID;
		}

		if ( null === $aggregate ) {
			return $this->one_off->repair( $event_id, $post_status );
		}

		$status = EventStatus::from(
			$this->sanitizer->status( get_post_meta( $event_id, EventMeta::STATUS, true ) )
		);

		try {
			$window = $this->windows->current();
		} catch ( RuntimeException ) {
			update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

			return OccurrenceIndexRepairStatus::FAILED;
		}

		if ( ! $this->recurring->project( $event_id, $aggregate, $status, $window ) ) {
			update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

			return OccurrenceIndexRepairStatus::FAILED;
		}

		return OccurrenceIndexRepairStatus::INDEXED;
	}
}
