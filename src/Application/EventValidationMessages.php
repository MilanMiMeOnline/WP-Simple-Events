<?php
/**
 * Translated event validation messages.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Maps stable error codes to actionable user-facing text.
 */
final class EventValidationMessages {
	/**
	 * Return a translated message for one error code.
	 *
	 * @param EventValidationError $error Stable error code.
	 */
	public function message( EventValidationError $error ): string {
		return match ( $error ) {
			EventValidationError::MISSING_START_DATE => __( 'Enter a start date before publishing this event.', 'wp-simple-events' ),
			EventValidationError::MISSING_START_TIME => __( 'Enter a start time, or mark the event as all day.', 'wp-simple-events' ),
			EventValidationError::INVALID_START => __( 'The event start date or time is invalid.', 'wp-simple-events' ),
			EventValidationError::INVALID_END => __( 'The event end date or time is invalid.', 'wp-simple-events' ),
			EventValidationError::INCOMPLETE_END => __( 'Enter both an end date and end time, or leave both empty.', 'wp-simple-events' ),
			EventValidationError::INVALID_DATE_RANGE => __( 'The event range is invalid. The end must not be before the start, and local times must exist unambiguously in the event timezone.', 'wp-simple-events' ),
			EventValidationError::INVALID_TIMEZONE => __( 'The event timezone is invalid. Check the WordPress site timezone.', 'wp-simple-events' ),
			EventValidationError::INVALID_STATUS => __( 'Select a valid event status.', 'wp-simple-events' ),
			EventValidationError::INVALID_LOCATION_URL => __( 'Enter a valid HTTP or HTTPS location URL.', 'wp-simple-events' ),
			EventValidationError::INVALID_EVENT_URL => __( 'Enter a valid HTTP or HTTPS event URL.', 'wp-simple-events' ),
		};
	}

	/**
	 * Return all translated messages.
	 *
	 * @param EventValidationError[] $errors Stable error codes.
	 * @return string[]
	 */
	public function messages( array $errors ): array {
		return array_map( $this->message( ... ), $errors );
	}
}
