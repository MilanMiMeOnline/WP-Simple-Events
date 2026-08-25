<?php
/**
 * Specific-dates recurrence schedule.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A bounded explicit set of unique local dates.
 */
final readonly class SpecificDatesSchedule implements RecurrenceDefinition {
	public const MAX_DATES = 1_000;

	/**
	 * Validate and sort explicit local dates.
	 *
	 * @param array<array-key, mixed> $dates Untrusted canonical Y-m-d dates.
	 * @throws InvalidArgumentException When dates are empty, excessive, invalid or duplicated.
	 */
	public static function from_dates( array $dates ): self {
		if ( array() === $dates || count( $dates ) > self::MAX_DATES ) {
			throw new InvalidArgumentException( 'A specific-dates schedule is empty or exceeds its supported limit.' );
		}

		$normalized = array();
		$timezone   = new DateTimeZone( 'UTC' );

		foreach ( $dates as $date ) {
			if ( ! is_string( $date ) || trim( $date ) !== $date ) {
				throw new InvalidArgumentException( 'A specific schedule date must be a canonical string.' );
			}

			$test = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );

			if ( false === $test || $test->format( 'Y-m-d' ) !== $date ) {
				throw new InvalidArgumentException( 'A specific schedule contains an invalid date.' );
			}

			if ( isset( $normalized[ $date ] ) ) {
				throw new InvalidArgumentException( 'A specific schedule cannot contain duplicate dates.' );
			}

			$normalized[ $date ] = true;
		}

		$sorted = array_keys( $normalized );
		sort( $sorted, SORT_STRING );

		return new self( $sorted );
	}

	/**
	 * Store already validated and sorted dates.
	 *
	 * @param string[] $dates Sorted canonical dates.
	 */
	private function __construct( private array $dates ) {}

	/**
	 * Return the sorted unique canonical dates.
	 *
	 * @return string[]
	 */
	public function dates(): array {
		return $this->dates;
	}
}
