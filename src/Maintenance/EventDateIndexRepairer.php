<?php
/**
 * Safe derived event date-index repair.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Maintenance;

use MiMe\WPSimpleEvents\Application\EventInput;
use MiMe\WPSimpleEvents\Application\EventPublicationPolicy;
use MiMe\WPSimpleEvents\Application\EventValidator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Rebuilds only derived UTC metadata from validated canonical event dates.
 */
final readonly class EventDateIndexRepairer {
	/**
	 * Create the repair service.
	 *
	 * @param EventValidator         $validator Central event validator.
	 * @param EventPublicationPolicy $policy    Publication completeness policy.
	 */
	public function __construct(
		private EventValidator $validator = new EventValidator(),
		private EventPublicationPolicy $policy = new EventPublicationPolicy()
	) {}

	/**
	 * Validate stored canonical dates and repair only their UTC indexes.
	 *
	 * @param int    $post_id     Event ID.
	 * @param string $post_status WordPress publication status.
	 */
	public function repair( int $post_id, string $post_status ): EventDateIndexRepairStatus {
		$start_local = $this->stored_string( $post_id, EventMeta::START_LOCAL );
		$end_local   = $this->stored_string( $post_id, EventMeta::END_LOCAL );

		if ( null === $start_local || null === $end_local ) {
			return EventDateIndexRepairStatus::INVALID;
		}

		if ( '' === $start_local ) {
			if ( '' !== $end_local || $this->policy->requires_date_range( $post_status ) ) {
				return EventDateIndexRepairStatus::INVALID;
			}

			return $this->clear_indexes( $post_id );
		}

		$all_day  = $this->stored_boolean( $post_id, EventMeta::ALL_DAY );
		$timezone = $this->stored_string( $post_id, EventMeta::TIMEZONE );

		if ( null === $all_day || null === $timezone || '' === $timezone ) {
			return EventDateIndexRepairStatus::INVALID;
		}

		$input  = EventInput::from_canonical(
			$start_local,
			$end_local,
			$all_day,
			$timezone,
			'',
			'',
			'',
			'',
			EventStatus::SCHEDULED->value
		);
		$result = $this->validator->validate(
			$input,
			$this->policy->requires_date_range( $post_status )
		);
		$data   = $result->data();
		$range  = null !== $data ? $data->date_range : null;

		if ( ! $result->is_valid() || null === $range ) {
			return EventDateIndexRepairStatus::INVALID;
		}

		$start_utc = $range->start_utc();
		$end_utc   = $range->end_utc();
		$start_ok  = $this->timestamp_matches( get_post_meta( $post_id, EventMeta::START_UTC, true ), $start_utc );
		$end_ok    = $this->timestamp_matches( get_post_meta( $post_id, EventMeta::END_UTC, true ), $end_utc );

		if ( $start_ok && $end_ok ) {
			return EventDateIndexRepairStatus::UNCHANGED;
		}

		if ( ! $start_ok && false === update_post_meta( $post_id, EventMeta::START_UTC, $start_utc ) ) {
			return EventDateIndexRepairStatus::FAILED;
		}

		if ( ! $end_ok && false === update_post_meta( $post_id, EventMeta::END_UTC, $end_utc ) ) {
			return EventDateIndexRepairStatus::FAILED;
		}

		return EventDateIndexRepairStatus::REPAIRED;
	}

	/**
	 * Remove stale indexes from a valid incomplete draft.
	 *
	 * @param int $post_id Event ID.
	 */
	private function clear_indexes( int $post_id ): EventDateIndexRepairStatus {
		$start_exists = metadata_exists( 'post', $post_id, EventMeta::START_UTC );
		$end_exists   = metadata_exists( 'post', $post_id, EventMeta::END_UTC );

		if ( ! $start_exists && ! $end_exists ) {
			return EventDateIndexRepairStatus::UNCHANGED;
		}

		if ( $start_exists && ! delete_post_meta( $post_id, EventMeta::START_UTC ) ) {
			return EventDateIndexRepairStatus::FAILED;
		}

		if ( $end_exists && ! delete_post_meta( $post_id, EventMeta::END_UTC ) ) {
			return EventDateIndexRepairStatus::FAILED;
		}

		return EventDateIndexRepairStatus::CLEARED;
	}

	/**
	 * Read one stored scalar string without normalizing corrupt structures.
	 *
	 * @param int    $post_id  Event ID.
	 * @param string $meta_key Metadata key.
	 */
	private function stored_string( int $post_id, string $meta_key ): ?string {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Read only supported stored boolean representations.
	 *
	 * @param int    $post_id  Event ID.
	 * @param string $meta_key Metadata key.
	 */
	private function stored_boolean( int $post_id, string $meta_key ): ?bool {
		return match ( get_post_meta( $post_id, $meta_key, true ) ) {
			true, 1, '1'     => true,
			false, 0, '0', '' => false,
			default           => null,
		};
	}

	/**
	 * Compare a WordPress integer meta representation with a derived timestamp.
	 *
	 * @param mixed $stored   Stored metadata value.
	 * @param int   $expected Expected derived timestamp.
	 */
	private function timestamp_matches( mixed $stored, int $expected ): bool {
		if ( is_int( $stored ) ) {
			return $expected === $stored;
		}

		return is_string( $stored )
			&& 1 === preg_match( '/^[0-9]+$/D', $stored )
			&& $expected === (int) $stored;
	}
}
