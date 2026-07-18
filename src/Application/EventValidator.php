<?php
/**
 * Central event validation service.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Sanitizes and validates event data for every write interface.
 */
final readonly class EventValidator {
	/**
	 * Create the central validator.
	 *
	 * @param EventMetaSanitizer $sanitizer Registered-meta compatible sanitizer.
	 */
	public function __construct( private EventMetaSanitizer $sanitizer = new EventMetaSanitizer() ) {}

	/**
	 * Validate event input.
	 *
	 * Drafts may omit their complete date range. Published states may not.
	 *
	 * @param EventInput $input              Untrusted event fields.
	 * @param bool       $date_range_required Whether publication requires a complete start.
	 */
	public function validate( EventInput $input, bool $date_range_required ): EventValidationResult {
		$errors       = array();
		$timezone     = $this->sanitizer->timezone( $input->timezone );
		$status       = EventStatus::tryFrom( trim( $input->status ) );
		$location_url = $this->sanitizer->url( trim( $input->location_url ) );
		$event_url    = $this->sanitizer->url( trim( $input->event_url ) );

		if ( '' === $timezone ) {
			$errors[] = EventValidationError::INVALID_TIMEZONE;
		}

		if ( null === $status ) {
			$errors[] = EventValidationError::INVALID_STATUS;
		}

		if ( '' !== trim( $input->location_url ) && '' === $location_url ) {
			$errors[] = EventValidationError::INVALID_LOCATION_URL;
		}

		if ( '' !== trim( $input->event_url ) && '' === $event_url ) {
			$errors[] = EventValidationError::INVALID_EVENT_URL;
		}

		$range_result = $this->validate_range( $input, $timezone, $date_range_required );
		$errors       = array_merge( $errors, $range_result['errors'] );

		if ( array() !== $errors || null === $status ) {
			return EventValidationResult::invalid( $errors );
		}

		return EventValidationResult::valid(
			new ValidatedEventData(
				$range_result['range'],
				$input->all_day,
				$timezone,
				$this->sanitizer->venue( $input->venue ),
				$this->sanitizer->address( $input->address ),
				$location_url,
				$event_url,
				$status
			)
		);
	}

	/**
	 * Validate and canonicalize the event range.
	 *
	 * @param EventInput $input              Untrusted event fields.
	 * @param string     $timezone           Sanitized timezone.
	 * @param bool       $date_range_required Whether publication requires a complete start.
	 * @return array{range: EventDateRange|null, errors: EventValidationError[]}
	 */
	private function validate_range( EventInput $input, string $timezone, bool $date_range_required ): array {
		$start_date = trim( $input->start_date );
		$start_time = trim( $input->start_time );
		$end_date   = trim( $input->end_date );
		$end_time   = trim( $input->end_time );
		$errors     = array();

		if ( '' === $start_date ) {
			if ( $date_range_required || '' !== $start_time || '' !== $end_date || '' !== $end_time ) {
				$errors[] = EventValidationError::MISSING_START_DATE;
			}

			return array(
				'range'  => null,
				'errors' => $errors,
			);
		}

		$canonical_start_date = $this->sanitizer->local_datetime( $start_date );

		if ( '' === $canonical_start_date || 10 !== strlen( $canonical_start_date ) ) {
			return array(
				'range'  => null,
				'errors' => array( EventValidationError::INVALID_START ),
			);
		}

		if ( $input->all_day ) {
			return $this->validate_all_day_range( $canonical_start_date, $end_date, $timezone );
		}

		if ( '' === $start_time ) {
			return array(
				'range'  => null,
				'errors' => array( EventValidationError::MISSING_START_TIME ),
			);
		}

		$canonical_start = $this->sanitizer->local_datetime( $canonical_start_date . 'T' . $start_time );

		if ( '' === $canonical_start || 10 === strlen( $canonical_start ) ) {
			return array(
				'range'  => null,
				'errors' => array( EventValidationError::INVALID_START ),
			);
		}

		if ( ( '' === $end_date ) !== ( '' === $end_time ) ) {
			return array(
				'range'  => null,
				'errors' => array( EventValidationError::INCOMPLETE_END ),
			);
		}

		$canonical_end = null;

		if ( '' !== $end_date && '' !== $end_time ) {
			$canonical_end = $this->sanitizer->local_datetime( $end_date . 'T' . $end_time );

			if ( '' === $canonical_end || 10 === strlen( $canonical_end ) ) {
				return array(
					'range'  => null,
					'errors' => array( EventValidationError::INVALID_END ),
				);
			}
		}

		return $this->create_range( $canonical_start, $canonical_end, false, $timezone );
	}

	/**
	 * Validate an inclusive all-day range.
	 *
	 * @param string $start_date Canonical start date.
	 * @param string $end_date   Optional end date.
	 * @param string $timezone   Sanitized timezone.
	 * @return array{range: EventDateRange|null, errors: EventValidationError[]}
	 */
	private function validate_all_day_range( string $start_date, string $end_date, string $timezone ): array {
		$canonical_end = null;

		if ( '' !== $end_date ) {
			$canonical_end = $this->sanitizer->local_datetime( $end_date );

			if ( '' === $canonical_end || 10 !== strlen( $canonical_end ) ) {
				return array(
					'range'  => null,
					'errors' => array( EventValidationError::INVALID_END ),
				);
			}
		}

		return $this->create_range( $start_date, $canonical_end, true, $timezone );
	}

	/**
	 * Convert canonical local fields into the domain range.
	 *
	 * @param string      $start_local Canonical start.
	 * @param string|null $end_local   Optional canonical end.
	 * @param bool        $all_day     All-day selection.
	 * @param string      $timezone    Sanitized timezone.
	 * @return array{range: EventDateRange|null, errors: EventValidationError[]}
	 */
	private function create_range(
		string $start_local,
		?string $end_local,
		bool $all_day,
		string $timezone
	): array {
		if ( '' === $timezone ) {
			return array(
				'range'  => null,
				'errors' => array(),
			);
		}

		try {
			$range = EventDateRange::from_local( $start_local, $end_local, $all_day, $timezone );
		} catch ( InvalidArgumentException ) {
			return array(
				'range'  => null,
				'errors' => array( EventValidationError::INVALID_DATE_RANGE ),
			);
		}

		return array(
			'range'  => $range,
			'errors' => array(),
		);
	}
}
