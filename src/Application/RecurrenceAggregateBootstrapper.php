<?php
/**
 * One-off event to recurrence aggregate bootstrap.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Recurrence\SpecificDatesSchedule;

/**
 * Revalidates canonical one-off metadata before recurrence is first enabled.
 */
final readonly class RecurrenceAggregateBootstrapper {
	/**
	 * Create the bootstrap service.
	 *
	 * @param EventValidator $validator Central canonical event validator.
	 */
	public function __construct( private EventValidator $validator = new EventValidator() ) {}

	/**
	 * Represent one complete dated event as a one-date recurrence aggregate.
	 *
	 * @param int $event_id Canonical event post ID.
	 * @throws InvalidArgumentException When stored one-off state is incomplete or corrupt.
	 */
	public function from_event( int $event_id ): RecurrenceAggregate {
		if ( $event_id <= 0 || EventPostType::POST_TYPE !== get_post_type( $event_id ) ) {
			throw new InvalidArgumentException( 'A recurrence bootstrap requires one canonical event.' );
		}

		$series_uid = $this->stored_string( $event_id, EventMeta::SERIES_UID );
		$start      = $this->stored_string( $event_id, EventMeta::START_LOCAL );
		$end        = $this->stored_string( $event_id, EventMeta::END_LOCAL );
		$timezone   = $this->stored_string( $event_id, EventMeta::TIMEZONE );
		$status     = $this->stored_string( $event_id, EventMeta::STATUS );
		$all_day    = $this->stored_boolean( $event_id, EventMeta::ALL_DAY );

		if ( null === $series_uid || null === $start || null === $end
			|| null === $timezone || null === $status || null === $all_day
		) {
			throw new InvalidArgumentException( 'The stored event details cannot initialize recurrence.' );
		}

		$result = $this->validator->validate(
			EventInput::from_canonical(
				$start,
				$end,
				$all_day,
				$timezone,
				'',
				'',
				'',
				'',
				'',
				$status
			),
			true
		);
		$data   = $result->data();

		if ( ! $result->is_valid() || null === $data || null === $data->date_range ) {
			throw new InvalidArgumentException( 'The stored event date cannot initialize recurrence.' );
		}

		$range = $data->date_range;

		return RecurrenceAggregate::create(
			$series_uid,
			$data->timezone,
			array(
				new ScheduleSegment(
					0,
					$range->start_local(),
					$range,
					SpecificDatesSchedule::from_dates( array( substr( $range->start_local(), 0, 10 ) ) )
				),
			)
		);
	}

	/**
	 * Read one scalar metadata value without normalizing corrupt structures.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $key      Metadata key.
	 */
	private function stored_string( int $event_id, string $key ): ?string {
		$value = get_post_meta( $event_id, $key, true );

		return is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Read only canonical WordPress boolean metadata representations.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $key      Metadata key.
	 */
	private function stored_boolean( int $event_id, string $key ): ?bool {
		return match ( get_post_meta( $event_id, $key, true ) ) {
			true, 1, '1'      => true,
			false, 0, '0', '' => false,
			default            => null,
		};
	}
}
