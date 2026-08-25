<?php
/**
 * Complete recurrence aggregate save coordination.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\RecurrenceOccurrenceProjector;
use MiMe\WPSimpleEvents\Occurrence\RecurringEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Saves canonical recurrence first, then repairs its complete derived projection.
 */
final readonly class RecurrenceSaveCoordinator {
	/**
	 * Create the save coordinator.
	 *
	 * @param RecurrenceAggregatePersistence    $persistence Authorized canonical persistence.
	 * @param RecurringEventOccurrenceProjector $projector   Complete derived-state projector.
	 * @param EventMetaSanitizer                $sanitizer   Canonical metadata sanitizer.
	 */
	public function __construct(
		private RecurrenceAggregatePersistence $persistence = new RecurrenceAggregatePersistence(),
		private RecurringEventOccurrenceProjector $projector = new RecurrenceOccurrenceProjector(),
		private EventMetaSanitizer $sanitizer = new EventMetaSanitizer()
	) {}

	/**
	 * Store one complete aggregate and activate one complete bounded projection.
	 *
	 * Unchanged clean aggregates do not rebuild. Unchanged dirty aggregates are
	 * deliberately projected again so a failed earlier attempt remains repairable.
	 *
	 * @param int                        $event_id         Canonical event post ID.
	 * @param RecurrenceAggregate        $aggregate        Validated complete aggregate.
	 * @param RecurrenceGenerationWindow $window           Explicit bounded projection window.
	 * @param string|null                $expected_revision Optional editor revision used for preview.
	 */
	public function save(
		int $event_id,
		RecurrenceAggregate $aggregate,
		RecurrenceGenerationWindow $window,
		?string $expected_revision = null
	): RecurrencePersistenceResult {
		$result = $this->persistence->replace( $event_id, $aggregate, $expected_revision );

		if ( ! $result->successful() ) {
			return $result;
		}

		if ( ! $result->changed() && ! $this->projection_is_dirty( $event_id ) ) {
			return $result;
		}

		$status = EventStatus::from(
			$this->sanitizer->status( get_post_meta( $event_id, EventMeta::STATUS, true ) )
		);

		if ( ! $this->projector->project( $event_id, $aggregate, $status, $window ) ) {
			return RecurrencePersistenceResult::failure(
				RecurrencePersistenceError::PROJECTION_FAILED,
				$result->changed()
			);
		}

		return $result;
	}

	/**
	 * Recognize only the canonical true representations used by WordPress metadata.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	private function projection_is_dirty( int $event_id ): bool {
		$value = get_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

		return true === $value || '1' === $value;
	}
}
