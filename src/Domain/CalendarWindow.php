<?php
/**
 * Public calendar request window.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

/**
 * Carries one absolute, non-empty and bounded half-open time interval.
 */
final readonly class CalendarWindow {
	public const MAX_RANGE_SECONDS = 34_560_000;

	private const ISO_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D';

	/**
	 * Store validated UTC boundaries.
	 *
	 * @param int $start_utc         Inclusive request start.
	 * @param int $end_exclusive_utc Exclusive request end.
	 * @throws InvalidArgumentException When the interval is empty or unbounded.
	 */
	public function __construct(
		public int $start_utc,
		public int $end_exclusive_utc
	) {
		if ( $start_utc < 0 || $end_exclusive_utc <= $start_utc ) {
			throw new InvalidArgumentException( 'The calendar window must have a positive duration.' );
		}

		if ( $end_exclusive_utc - $start_utc > self::MAX_RANGE_SECONDS ) {
			throw new InvalidArgumentException( 'The calendar window exceeds four hundred days.' );
		}
	}

	/**
	 * Parse strict ISO 8601 boundaries with an explicit timezone.
	 *
	 * @param string $start Inclusive ISO 8601 start.
	 * @param string $end   Exclusive ISO 8601 end.
	 * @throws InvalidArgumentException When either boundary is malformed.
	 */
	public static function from_iso( string $start, string $end ): self {
		return new self( self::timestamp_from_iso( $start ), self::timestamp_from_iso( $end ) );
	}

	/**
	 * Convert one strict ISO boundary to a Unix timestamp.
	 *
	 * @param string $value ISO 8601 boundary.
	 * @throws InvalidArgumentException When the value is ambiguous or invalid.
	 */
	public static function timestamp_from_iso( string $value ): int {
		if ( 1 !== preg_match( self::ISO_PATTERN, $value ) ) {
			throw new InvalidArgumentException( 'Calendar boundaries require an ISO 8601 timezone.' );
		}

		try {
			$date   = new DateTimeImmutable( $value );
			$errors = DateTimeImmutable::getLastErrors();
		} catch ( Exception ) {
			throw new InvalidArgumentException( 'The calendar boundary is not a valid date-time.' );
		}

		if ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
			throw new InvalidArgumentException( 'The calendar boundary is not a valid date-time.' );
		}

		return $date->getTimestamp();
	}
}
