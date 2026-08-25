<?php
/**
 * Canonical recurrence definition encoding.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;

/**
 * Converts only validated plugin-owned schedules to and from a strict array shape.
 */
final readonly class RecurrenceDefinitionCodec {
	/**
	 * Encode one validated definition into its canonical storage shape.
	 *
	 * @param RecurrenceDefinition $definition Factory-validated schedule.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When the definition implementation is unsupported.
	 */
	public function encode( RecurrenceDefinition $definition ): array {
		if ( $definition instanceof SpecificDatesSchedule ) {
			return array(
				'type'  => 'specific_dates',
				'dates' => $definition->dates(),
			);
		}

		if ( ! $definition instanceof RecurrenceRule ) {
			throw new InvalidArgumentException( 'The recurrence definition type cannot be encoded.' );
		}

		$encoded = array(
			'type'      => 'rule',
			'frequency' => $definition->frequency()->value,
			'interval'  => $definition->interval(),
			'end'       => $this->encode_end( $definition->end() ),
		);

		if ( RecurrenceFrequency::WEEKLY === $definition->frequency() ) {
			$encoded['weekdays'] = $definition->weekdays();
		} elseif ( RecurrenceFrequency::MONTHLY === $definition->frequency() ) {
			$encoded['monthly_mode'] = $definition->monthly_mode()?->value;

			if ( MonthlyRecurrenceMode::DAY_OF_MONTH === $definition->monthly_mode() ) {
				$encoded['month_day'] = $definition->month_day();
			} else {
				$encoded['ordinal'] = $definition->ordinal();
				$encoded['weekday'] = $definition->weekday();
			}
		} elseif ( RecurrenceFrequency::YEARLY === $definition->frequency() ) {
			$encoded['month']     = $definition->month();
			$encoded['month_day'] = $definition->month_day();
		}

		return $encoded;
	}

	/**
	 * Decode one complete untrusted storage value through the domain factories.
	 *
	 * @param mixed $value Untrusted metadata value.
	 * @throws InvalidArgumentException When the value is unknown, incomplete or invalid.
	 */
	public function decode( mixed $value ): RecurrenceDefinition {
		if ( ! is_array( $value ) || ! $this->is_string_keyed( $value ) ) {
			throw new InvalidArgumentException( 'A recurrence definition must be a keyed array.' );
		}

		$type = $value['type'] ?? null;

		if ( 'specific_dates' === $type ) {
			$this->require_keys( $value, array( 'type', 'dates' ) );

			if ( ! is_array( $value['dates'] ) ) {
				throw new InvalidArgumentException( 'Specific recurrence dates must be an array.' );
			}

			return SpecificDatesSchedule::from_dates( $value['dates'] );
		}

		if ( 'rule' !== $type ) {
			throw new InvalidArgumentException( 'The recurrence definition type is unsupported.' );
		}

		$frequency = $value['frequency'] ?? null;
		$interval  = $value['interval'] ?? null;
		$end       = $this->decode_end( $value['end'] ?? null );

		if ( ! is_string( $frequency ) || ! is_int( $interval ) ) {
			throw new InvalidArgumentException( 'The recurrence rule frequency or interval is invalid.' );
		}

		return match ( RecurrenceFrequency::tryFrom( $frequency ) ) {
			RecurrenceFrequency::DAILY   => $this->decode_daily( $value, $interval, $end ),
			RecurrenceFrequency::WEEKLY  => $this->decode_weekly( $value, $interval, $end ),
			RecurrenceFrequency::MONTHLY => $this->decode_monthly( $value, $interval, $end ),
			RecurrenceFrequency::YEARLY  => $this->decode_yearly( $value, $interval, $end ),
			default                       => throw new InvalidArgumentException( 'The recurrence rule frequency is unsupported.' ),
		};
	}

	/**
	 * Encode an explicit termination variant.
	 *
	 * @param RecurrenceEnd $end Validated termination condition.
	 * @return array<string, int|string>
	 */
	private function encode_end( RecurrenceEnd $end ): array {
		if ( null !== $end->until_date() ) {
			return array(
				'mode' => 'until',
				'date' => $end->until_date(),
			);
		}

		if ( null !== $end->count() ) {
			return array(
				'mode'  => 'count',
				'count' => $end->count(),
			);
		}

		return array( 'mode' => 'never' );
	}

	/**
	 * Decode one complete termination variant.
	 *
	 * @param mixed $value Untrusted termination value.
	 * @throws InvalidArgumentException When the variant is incomplete or invalid.
	 */
	private function decode_end( mixed $value ): RecurrenceEnd {
		if ( ! is_array( $value ) || ! $this->is_string_keyed( $value ) ) {
			throw new InvalidArgumentException( 'A recurrence end must be a keyed array.' );
		}

		$mode = $value['mode'] ?? null;

		if ( 'never' === $mode ) {
			$this->require_keys( $value, array( 'mode' ) );

			return RecurrenceEnd::never();
		}

		if ( 'until' === $mode ) {
			$this->require_keys( $value, array( 'mode', 'date' ) );

			if ( ! is_string( $value['date'] ) ) {
				throw new InvalidArgumentException( 'A recurrence end date must be a string.' );
			}

			return RecurrenceEnd::on( $value['date'] );
		}

		if ( 'count' === $mode ) {
			$this->require_keys( $value, array( 'mode', 'count' ) );

			if ( ! is_int( $value['count'] ) ) {
				throw new InvalidArgumentException( 'A recurrence end count must be an integer.' );
			}

			return RecurrenceEnd::after( $value['count'] );
		}

		throw new InvalidArgumentException( 'The recurrence end mode is unsupported.' );
	}

	/**
	 * Decode a daily rule after shared fields were validated.
	 *
	 * @param array<string, mixed> $value    Complete rule value.
	 * @param int                  $interval Positive interval candidate.
	 * @param RecurrenceEnd        $end      Validated termination.
	 * @throws InvalidArgumentException When the daily fields are incomplete or invalid.
	 */
	private function decode_daily( array $value, int $interval, RecurrenceEnd $end ): RecurrenceRule {
		$this->require_keys( $value, array( 'type', 'frequency', 'interval', 'end' ) );

		return RecurrenceRule::daily( $interval, $end );
	}

	/**
	 * Decode a weekly rule after shared fields were validated.
	 *
	 * @param array<string, mixed> $value    Complete rule value.
	 * @param int                  $interval Positive interval candidate.
	 * @param RecurrenceEnd        $end      Validated termination.
	 * @throws InvalidArgumentException When the weekly fields are incomplete or invalid.
	 */
	private function decode_weekly( array $value, int $interval, RecurrenceEnd $end ): RecurrenceRule {
		$this->require_keys( $value, array( 'type', 'frequency', 'interval', 'end', 'weekdays' ) );

		if ( ! is_array( $value['weekdays'] ) ) {
			throw new InvalidArgumentException( 'Weekly recurrence weekdays must be an array.' );
		}

		return RecurrenceRule::weekly( $value['weekdays'], $interval, $end );
	}

	/**
	 * Decode one strict monthly rule variant.
	 *
	 * @param array<string, mixed> $value    Complete rule value.
	 * @param int                  $interval Positive interval candidate.
	 * @param RecurrenceEnd        $end      Validated termination.
	 * @throws InvalidArgumentException When the monthly fields are incomplete or invalid.
	 */
	private function decode_monthly( array $value, int $interval, RecurrenceEnd $end ): RecurrenceRule {
		$mode = $value['monthly_mode'] ?? null;

		if ( MonthlyRecurrenceMode::DAY_OF_MONTH->value === $mode ) {
			$this->require_keys( $value, array( 'type', 'frequency', 'interval', 'end', 'monthly_mode', 'month_day' ) );

			if ( ! is_int( $value['month_day'] ) ) {
				throw new InvalidArgumentException( 'A monthly recurrence day must be an integer.' );
			}

			return RecurrenceRule::monthly_on_day( $value['month_day'], $interval, $end );
		}

		if ( MonthlyRecurrenceMode::ORDINAL_WEEKDAY->value === $mode ) {
			$this->require_keys( $value, array( 'type', 'frequency', 'interval', 'end', 'monthly_mode', 'ordinal', 'weekday' ) );

			if ( ! is_int( $value['ordinal'] ) || ! is_int( $value['weekday'] ) ) {
				throw new InvalidArgumentException( 'A monthly recurrence ordinal and weekday must be integers.' );
			}

			return RecurrenceRule::monthly_on_ordinal_weekday(
				$value['ordinal'],
				$value['weekday'],
				$interval,
				$end
			);
		}

		throw new InvalidArgumentException( 'The monthly recurrence mode is unsupported.' );
	}

	/**
	 * Decode a yearly rule after shared fields were validated.
	 *
	 * @param array<string, mixed> $value    Complete rule value.
	 * @param int                  $interval Positive interval candidate.
	 * @param RecurrenceEnd        $end      Validated termination.
	 * @throws InvalidArgumentException When the yearly fields are incomplete or invalid.
	 */
	private function decode_yearly( array $value, int $interval, RecurrenceEnd $end ): RecurrenceRule {
		$this->require_keys( $value, array( 'type', 'frequency', 'interval', 'end', 'month', 'month_day' ) );

		if ( ! is_int( $value['month'] ) || ! is_int( $value['month_day'] ) ) {
			throw new InvalidArgumentException( 'A yearly recurrence month and day must be integers.' );
		}

		return RecurrenceRule::yearly_on( $value['month'], $value['month_day'], $interval, $end );
	}

	/**
	 * Require an exact key set so future or malicious fields never survive silently.
	 *
	 * @param array<string, mixed> $value         Untrusted keyed value.
	 * @param array                $required_keys Exact allowed and required keys.
	 * @phpstan-param list<string> $required_keys
	 * @throws InvalidArgumentException When a key is missing or unknown.
	 */
	private function require_keys( array $value, array $required_keys ): void {
		$actual_keys = array_keys( $value );
		sort( $actual_keys, SORT_STRING );
		sort( $required_keys, SORT_STRING );

		if ( $actual_keys !== $required_keys ) {
			throw new InvalidArgumentException( 'The recurrence definition contains missing or unknown fields.' );
		}
	}

	/**
	 * Confirm that a PHP array does not mix numeric and object-style keys.
	 *
	 * @param array<array-key, mixed> $value Untrusted array.
	 */
	private function is_string_keyed( array $value ): bool {
		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_string( $key ) ) {
				return false;
			}
		}

		return true;
	}
}
