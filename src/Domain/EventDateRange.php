<?php
/**
 * Canonical event date range.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Validates local event dates and derives stable UTC timestamps.
 */
final readonly class EventDateRange {
	private const DATE_FORMAT     = 'Y-m-d';
	private const DATETIME_FORMAT = 'Y-m-d\TH:i:s';
	private const SECONDS_PER_DAY = 86_400;

	/**
	 * Create a validated date range.
	 *
	 * Timed values use ISO local format `Y-m-d\TH:i[:s]`. All-day values use
	 * inclusive `Y-m-d` dates. A missing end uses the start value.
	 *
	 * @param string      $start_local Local start value.
	 * @param string|null $end_local   Local end value, or null.
	 * @param bool        $all_day     Whether this is an all-day event.
	 * @param string      $timezone    IANA name or WordPress fixed UTC offset.
	 * @throws InvalidArgumentException When a value or range is invalid.
	 */
	public static function from_local(
		string $start_local,
		?string $end_local,
		bool $all_day,
		string $timezone
	): self {
		$timezone_object = self::create_timezone( $timezone );

		if ( $all_day ) {
			$start_local = self::normalize_date( $start_local );
			$end_local   = self::normalize_date( null === $end_local || '' === $end_local ? $start_local : $end_local );
			$start       = self::parse_exact( $start_local, self::DATE_FORMAT, $timezone_object );
			$end         = self::parse_exact( $end_local, self::DATE_FORMAT, $timezone_object )
				->setTime( 23, 59, 59 );
		} else {
			$start_local = self::normalize_datetime( $start_local );
			$end_local   = self::normalize_datetime( null === $end_local || '' === $end_local ? $start_local : $end_local );
			$start       = self::parse_exact( $start_local, self::DATETIME_FORMAT, $timezone_object );
			$end         = self::parse_exact( $end_local, self::DATETIME_FORMAT, $timezone_object );
		}

		if ( $end < $start ) {
			throw new InvalidArgumentException( 'The event end cannot be before its start.' );
		}

		return new self(
			$start_local,
			$end_local,
			$all_day,
			$timezone_object->getName(),
			$start->getTimestamp(),
			$end->getTimestamp()
		);
	}

	/**
	 * Store an already validated canonical range.
	 *
	 * @param string $start_local Canonical local start.
	 * @param string $end_local   Canonical local end.
	 * @param bool   $all_day     All-day flag.
	 * @param string $timezone    Canonical timezone identifier.
	 * @param int    $start_utc   UTC start timestamp.
	 * @param int    $end_utc     UTC end timestamp.
	 */
	private function __construct(
		private string $start_local,
		private string $end_local,
		private bool $all_day,
		private string $timezone,
		private int $start_utc,
		private int $end_utc
	) {}

	/**
	 * Return the canonical local start value.
	 */
	public function start_local(): string {
		return $this->start_local;
	}

	/**
	 * Return the canonical local end value.
	 */
	public function end_local(): string {
		return $this->end_local;
	}

	/**
	 * Determine whether the event lasts all day.
	 */
	public function all_day(): bool {
		return $this->all_day;
	}

	/**
	 * Return the stored timezone identifier.
	 */
	public function timezone(): string {
		return $this->timezone;
	}

	/**
	 * Return the UTC start timestamp.
	 */
	public function start_utc(): int {
		return $this->start_utc;
	}

	/**
	 * Return the inclusive UTC end timestamp.
	 */
	public function end_utc(): int {
		return $this->end_utc;
	}

	/**
	 * Create a safe timezone instance.
	 *
	 * @param string $timezone Timezone identifier.
	 * @throws InvalidArgumentException When the timezone is unsupported.
	 */
	private static function create_timezone( string $timezone ): DateTimeZone {
		$timezone = trim( $timezone );

		if ( '' === $timezone || strlen( $timezone ) > 64 ) {
			throw new InvalidArgumentException( 'A valid event timezone is required.' );
		}

		$iana_timezone    = in_array( $timezone, timezone_identifiers_list(), true );
		$fixed_utc_offset = 1 === preg_match( '/^[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00)$/D', $timezone );

		if ( ! $iana_timezone && ! $fixed_utc_offset ) {
			throw new InvalidArgumentException( 'The event timezone is not supported.' );
		}

		return new DateTimeZone( $timezone );
	}

	/**
	 * Normalize an inclusive all-day date.
	 *
	 * @param string $value Raw date.
	 * @throws InvalidArgumentException When the date format is invalid.
	 */
	private static function normalize_date( string $value ): string {
		$value = trim( $value );

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
			throw new InvalidArgumentException( 'All-day event dates must use Y-m-d.' );
		}

		return $value;
	}

	/**
	 * Normalize a timed local value to include seconds.
	 *
	 * @param string $value Raw local date and time.
	 * @throws InvalidArgumentException When the date-time format is invalid.
	 */
	private static function normalize_datetime( string $value ): string {
		$value = str_replace( ' ', 'T', trim( $value ) );

		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value ) ) {
			return $value . ':00';
		}

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/D', $value ) ) {
			throw new InvalidArgumentException( 'Timed event values must use Y-m-d\TH:i[:s].' );
		}

		return $value;
	}

	/**
	 * Parse without allowing PHP to silently normalize invalid or missing times.
	 *
	 * @param string       $value    Canonical local value.
	 * @param string       $format   Expected local format.
	 * @param DateTimeZone $timezone Event timezone.
	 * @throws InvalidArgumentException When PHP normalizes an invalid local value.
	 */
	private static function parse_exact( string $value, string $format, DateTimeZone $timezone ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, $timezone );

		if ( false === $date || $date->format( $format ) !== $value ) {
			throw new InvalidArgumentException( 'The event contains an invalid local date or time.' );
		}

		if ( self::DATETIME_FORMAT === $format && self::is_ambiguous( $value, $timezone ) ) {
			throw new InvalidArgumentException( 'The event local time is ambiguous during a daylight-saving transition.' );
		}

		return $date;
	}

	/**
	 * Detect a repeated local clock interval during a backward DST transition.
	 *
	 * @param string       $value    Canonical local date-time.
	 * @param DateTimeZone $timezone Event timezone.
	 */
	private static function is_ambiguous( string $value, DateTimeZone $timezone ): bool {
		if ( false === $timezone->getLocation() ) {
			return false;
		}

		$wall_clock = DateTimeImmutable::createFromFormat(
			'!' . self::DATETIME_FORMAT,
			$value,
			new DateTimeZone( 'UTC' )
		);

		if ( false === $wall_clock ) {
			return false;
		}

		$wall_timestamp = $wall_clock->getTimestamp();
		$transitions    = $timezone->getTransitions(
			$wall_timestamp - self::SECONDS_PER_DAY,
			$wall_timestamp + self::SECONDS_PER_DAY
		);

		$previous_offset = null;

		foreach ( $transitions as $transition ) {
			$current_offset = $transition['offset'];

			if ( null !== $previous_offset && $current_offset < $previous_offset ) {
				$repeated_start = $transition['ts'] + $current_offset;
				$repeated_end   = $transition['ts'] + $previous_offset;

				if ( $wall_timestamp >= $repeated_start && $wall_timestamp < $repeated_end ) {
					return true;
				}
			}

			$previous_offset = $current_offset;
		}

		return false;
	}
}
