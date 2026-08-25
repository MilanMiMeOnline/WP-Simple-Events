<?php
/**
 * Validated recurrence rule.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;

/**
 * Plugin-owned rule values without exposing raw RFC 5545 syntax.
 */
final readonly class RecurrenceRule implements RecurrenceDefinition {
	public const MAX_INTERVAL = 999;

	/**
	 * Build a daily rule.
	 *
	 * @param int                $interval Positive day interval.
	 * @param RecurrenceEnd|null $end      Optional termination condition.
	 */
	public static function daily( int $interval = 1, ?RecurrenceEnd $end = null ): self {
		return self::make( RecurrenceFrequency::DAILY, $interval, $end );
	}

	/**
	 * Build a weekly rule with ISO weekdays (Monday=1, Sunday=7).
	 *
	 * @param array<array-key, mixed> $weekdays Untrusted selected ISO weekdays.
	 * @param int                     $interval Positive week interval.
	 * @param RecurrenceEnd|null      $end      Optional termination condition.
	 * @throws InvalidArgumentException When weekdays or interval are invalid.
	 */
	public static function weekly( array $weekdays, int $interval = 1, ?RecurrenceEnd $end = null ): self {
		if ( array() === $weekdays || count( $weekdays ) > 7 ) {
			throw new InvalidArgumentException( 'A weekly recurrence requires between one and seven weekdays.' );
		}

		$normalized = array();

		foreach ( $weekdays as $weekday ) {
			if ( ! is_int( $weekday ) || $weekday < 1 || $weekday > 7 ) {
				throw new InvalidArgumentException( 'A recurrence weekday must be an integer from one to seven.' );
			}

			$normalized[] = $weekday;
		}

		$unique = array_values( array_unique( $normalized, SORT_NUMERIC ) );

		if ( count( $unique ) !== count( $normalized ) ) {
			throw new InvalidArgumentException( 'A weekly recurrence cannot contain duplicate weekdays.' );
		}

		$normalized = $unique;
		sort( $normalized, SORT_NUMERIC );

		return self::make( RecurrenceFrequency::WEEKLY, $interval, $end, $normalized );
	}

	/**
	 * Build a monthly same-calendar-day rule.
	 *
	 * @param int                $month_day Calendar day from one through thirty-one.
	 * @param int                $interval  Positive month interval.
	 * @param RecurrenceEnd|null $end       Optional termination condition.
	 * @throws InvalidArgumentException When calendar day or interval are invalid.
	 */
	public static function monthly_on_day( int $month_day, int $interval = 1, ?RecurrenceEnd $end = null ): self {
		if ( $month_day < 1 || $month_day > 31 ) {
			throw new InvalidArgumentException( 'A monthly calendar day must be between one and thirty-one.' );
		}

		return self::make(
			RecurrenceFrequency::MONTHLY,
			$interval,
			$end,
			array(),
			MonthlyRecurrenceMode::DAY_OF_MONTH,
			$month_day
		);
	}

	/**
	 * Build a monthly ordinal-weekday rule.
	 *
	 * @param int                $ordinal One through five, or -1 for last.
	 * @param int                $weekday ISO weekday (Monday=1, Sunday=7).
	 * @param int                $interval Positive month interval.
	 * @param RecurrenceEnd|null $end Optional termination condition.
	 * @throws InvalidArgumentException When ordinal, weekday or interval are invalid.
	 */
	public static function monthly_on_ordinal_weekday(
		int $ordinal,
		int $weekday,
		int $interval = 1,
		?RecurrenceEnd $end = null
	): self {
		if ( ! in_array( $ordinal, array( -1, 1, 2, 3, 4, 5 ), true ) ) {
			throw new InvalidArgumentException( 'A monthly weekday ordinal must be first through fifth or last.' );
		}

		if ( $weekday < 1 || $weekday > 7 ) {
			throw new InvalidArgumentException( 'A recurrence weekday must be between one and seven.' );
		}

		return self::make(
			RecurrenceFrequency::MONTHLY,
			$interval,
			$end,
			array(),
			MonthlyRecurrenceMode::ORDINAL_WEEKDAY,
			null,
			$ordinal,
			$weekday
		);
	}

	/**
	 * Build a yearly same-month-and-day rule.
	 *
	 * @param int                $month     Calendar month.
	 * @param int                $month_day Calendar day.
	 * @param int                $interval  Positive year interval.
	 * @param RecurrenceEnd|null $end       Optional termination condition.
	 * @throws InvalidArgumentException When month, day or interval are invalid.
	 */
	public static function yearly_on( int $month, int $month_day, int $interval = 1, ?RecurrenceEnd $end = null ): self {
		if ( ! checkdate( $month, $month_day, 2000 ) ) {
			throw new InvalidArgumentException( 'A yearly recurrence month and day are invalid.' );
		}

		return self::make(
			RecurrenceFrequency::YEARLY,
			$interval,
			$end,
			array(),
			null,
			$month_day,
			null,
			null,
			$month
		);
	}

	/**
	 * Validate shared fields and construct one rule variant.
	 *
	 * @param RecurrenceFrequency        $frequency    Supported recurrence frequency.
	 * @param int                        $interval     Positive frequency interval.
	 * @param RecurrenceEnd|null         $end          Optional termination condition.
	 * @param int[]                      $weekdays     ISO weekday allowlist.
	 * @param MonthlyRecurrenceMode|null $monthly_mode Monthly calculation mode.
	 * @param int|null                   $month_day    Calendar day.
	 * @param int|null                   $ordinal      Weekday ordinal.
	 * @param int|null                   $weekday      ISO weekday.
	 * @param int|null                   $month        Calendar month.
	 * @throws InvalidArgumentException When the interval is invalid.
	 */
	private static function make(
		RecurrenceFrequency $frequency,
		int $interval,
		?RecurrenceEnd $end = null,
		array $weekdays = array(),
		?MonthlyRecurrenceMode $monthly_mode = null,
		?int $month_day = null,
		?int $ordinal = null,
		?int $weekday = null,
		?int $month = null
	): self {
		if ( $interval < 1 || $interval > self::MAX_INTERVAL ) {
			throw new InvalidArgumentException( 'A recurrence interval is outside the supported range.' );
		}

		return new self(
			$frequency,
			$interval,
			$end ?? RecurrenceEnd::never(),
			$weekdays,
			$monthly_mode,
			$month_day,
			$ordinal,
			$weekday,
			$month
		);
	}

	/**
	 * Store one factory-validated rule.
	 *
	 * @param RecurrenceFrequency        $frequency    Supported recurrence frequency.
	 * @param int                        $interval     Positive frequency interval.
	 * @param RecurrenceEnd              $end          Validated termination condition.
	 * @param int[]                      $weekdays     Selected ISO weekdays.
	 * @param MonthlyRecurrenceMode|null $monthly_mode Monthly calculation mode.
	 * @param int|null                   $month_day    Calendar day.
	 * @param int|null                   $ordinal      Weekday ordinal.
	 * @param int|null                   $weekday      ISO weekday.
	 * @param int|null                   $month        Calendar month.
	 */
	private function __construct(
		private RecurrenceFrequency $frequency,
		private int $interval,
		private RecurrenceEnd $end,
		private array $weekdays,
		private ?MonthlyRecurrenceMode $monthly_mode,
		private ?int $month_day,
		private ?int $ordinal,
		private ?int $weekday,
		private ?int $month
	) {}

	/**
	 * Return the supported recurrence frequency.
	 */
	public function frequency(): RecurrenceFrequency {
		return $this->frequency;
	}

	/**
	 * Return the positive frequency interval.
	 */
	public function interval(): int {
		return $this->interval;
	}

	/**
	 * Return the validated termination condition.
	 */
	public function end(): RecurrenceEnd {
		return $this->end;
	}

	/**
	 * Return selected ISO weekdays.
	 *
	 * @return int[]
	 */
	public function weekdays(): array {
		return $this->weekdays;
	}

	/**
	 * Return the monthly calculation mode when applicable.
	 */
	public function monthly_mode(): ?MonthlyRecurrenceMode {
		return $this->monthly_mode;
	}

	/**
	 * Return the configured calendar day when applicable.
	 */
	public function month_day(): ?int {
		return $this->month_day;
	}

	/**
	 * Return the configured weekday ordinal when applicable.
	 */
	public function ordinal(): ?int {
		return $this->ordinal;
	}

	/**
	 * Return the configured ISO weekday when applicable.
	 */
	public function weekday(): ?int {
		return $this->weekday;
	}

	/**
	 * Return the configured calendar month when applicable.
	 */
	public function month(): ?int {
		return $this->month;
	}
}
