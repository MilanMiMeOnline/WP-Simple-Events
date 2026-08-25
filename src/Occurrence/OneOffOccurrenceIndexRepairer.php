<?php
/**
 * Existing one-off event occurrence repair.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Application\EventInput;
use MiMe\WPSimpleEvents\Application\EventPublicationPolicy;
use MiMe\WPSimpleEvents\Application\EventValidator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Validates canonical stored values before building a one-off projection.
 */
final readonly class OneOffOccurrenceIndexRepairer implements OccurrenceIndexRepairService {
	/**
	 * Create the repair service.
	 *
	 * @param EventValidator           $validator Central event validator.
	 * @param EventPublicationPolicy   $policy    Publication completeness policy.
	 * @param EventOccurrenceProjector $projector Safe projection writer.
	 */
	public function __construct(
		private EventValidator $validator = new EventValidator(),
		private EventPublicationPolicy $policy = new EventPublicationPolicy(),
		private EventOccurrenceProjector $projector = new OneOffOccurrenceProjector()
	) {}

	/**
	 * Index one event only from its validated canonical metadata.
	 *
	 * @param int    $event_id   Event post ID.
	 * @param string $post_status WordPress publication status.
	 */
	public function repair( int $event_id, string $post_status ): OccurrenceIndexRepairStatus {
		if ( $event_id <= 0 ) {
			return OccurrenceIndexRepairStatus::FAILED;
		}

		$generation = ( new EventMetaSanitizer() )->generation(
			get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true )
		);
		$dirty      = $this->stored_boolean( $event_id, EventMeta::INDEX_DIRTY );

		if ( $generation > 0 && false === $dirty ) {
			return OccurrenceIndexRepairStatus::UNCHANGED;
		}

		$start_local = $this->stored_string( $event_id, EventMeta::START_LOCAL );
		$end_local   = $this->stored_string( $event_id, EventMeta::END_LOCAL );
		$all_day     = $this->stored_boolean( $event_id, EventMeta::ALL_DAY );
		$timezone    = $this->stored_string( $event_id, EventMeta::TIMEZONE );

		if ( null === $start_local || null === $end_local || null === $all_day || null === $timezone ) {
			return $this->invalid( $event_id );
		}

		if ( '' === $start_local && ! $this->policy->requires_date_range( $post_status ) ) {
			if ( ! $this->projector->project_one_off( $event_id, null, EventStatus::SCHEDULED ) ) {
				update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

				return OccurrenceIndexRepairStatus::FAILED;
			}

			return OccurrenceIndexRepairStatus::INDEXED;
		}

		$status = ( new EventMetaSanitizer() )->status(
			get_post_meta( $event_id, EventMeta::STATUS, true )
		);
		$input  = EventInput::from_canonical(
			$start_local,
			$end_local,
			$all_day,
			$timezone,
			'',
			'',
			'',
			'',
			'',
			$status
		);
		$result = $this->validator->validate(
			$input,
			$this->policy->requires_date_range( $post_status )
		);
		$data   = $result->data();

		if ( ! $result->is_valid() || null === $data || null === $data->date_range ) {
			return $this->invalid( $event_id );
		}

		if ( ! $this->projector->project_one_off( $event_id, $data->date_range, $data->status ) ) {
			update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

			return OccurrenceIndexRepairStatus::FAILED;
		}

		return OccurrenceIndexRepairStatus::INDEXED;
	}

	/**
	 * Mark invalid legacy data for deliberate administrator repair.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function invalid( int $event_id ): OccurrenceIndexRepairStatus {
		update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

		return OccurrenceIndexRepairStatus::INVALID;
	}

	/**
	 * Read one stored scalar string without normalizing corrupt structures.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Metadata key.
	 */
	private function stored_string( int $event_id, string $meta_key ): ?string {
		$value = get_post_meta( $event_id, $meta_key, true );

		return is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Read only supported stored boolean representations.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Metadata key.
	 */
	private function stored_boolean( int $event_id, string $meta_key ): ?bool {
		return match ( get_post_meta( $event_id, $meta_key, true ) ) {
			true, 1, '1'     => true,
			false, 0, '0', '' => false,
			default           => null,
		};
	}
}
