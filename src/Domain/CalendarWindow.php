<?php
/**
 * Public calendar request window.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * Carries one day-aligned, non-empty and bounded wall-time interval.
 */
final readonly class CalendarWindow {
	public const MAX_RANGE_SECONDS = 34_560_000;

	private const ISO_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))$/D';

	/**
	 * Store validated inclusive/exclusive local calendar boundaries.
	 *
	 * @param string $start_local         Inclusive local date.
	 * @param string $end_exclusive_local Exclusive local date.
	 * @throws InvalidArgumentException When the interval is empty or unbounded.
	 */
	public function __construct(
		public string $start_local,
		public string $end_exclusive_local
	) {
		$start_timestamp = self::timestamp_from_local_date( $start_local );
		$end_timestamp   = self::timestamp_from_local_date( $end_exclusive_local );

		if ( $end_timestamp <= $start_timestamp ) {
			throw new InvalidArgumentException( 'The calendar window must have a positive duration.' );
		}

		if ( $end_timestamp - $start_timestamp > self::MAX_RANGE_SECONDS ) {
			throw new InvalidArgumentException( 'The calendar window exceeds four hundred days.' );
		}
	}

	/**
	 * Parse strict, day-aligned ISO 8601 boundaries with an explicit timezone.
	 *
	 * @param string $start Inclusive ISO 8601 start.
	 * @param string $end   Exclusive ISO 8601 end.
	 * @throws InvalidArgumentException When either boundary is malformed.
	 */
	public static function from_iso( string $start, string $end ): self {
		return new self( self::local_date_from_iso( $start ), self::local_date_from_iso( $end ) );
	}

	/**
	 * Validate one transport boundary and return its unconverted local date.
	 *
	 * @param string $value ISO 8601 day boundary.
	 * @throws InvalidArgumentException When the boundary is malformed or partial.
	 */
	public static function local_date_from_iso( string $value ): string {
		self::timestamp_from_iso( $value );

		if ( ! self::is_midnight( $value ) ) {
			throw new InvalidArgumentException( 'Calendar boundaries must align to a local day.' );
		}

		return substr( $value, 0, 10 );
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

	/**
	 * Determine whether an ISO transport boundary represents local midnight.
	 *
	 * @param string $value Validated ISO 8601 boundary.
	 */
	private static function is_midnight( string $value ): bool {
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T00:00:00(?:\.0{1,6})?(?:Z|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))$/D', $value );
	}

	/**
	 * Parse one canonical local date independently from a machine timezone.
	 *
	 * @param string $value Canonical local date.
	 * @throws InvalidArgumentException When the date is malformed.
	 */
	private static function timestamp_from_local_date( string $value ): int {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			throw new InvalidArgumentException( 'Calendar boundaries require a valid local date.' );
		}

		return $date->getTimestamp();
	}
}
