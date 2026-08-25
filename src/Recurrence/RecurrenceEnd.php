<?php
/**
 * Recurrence termination condition.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Allows exactly one of an inclusive local end date or an occurrence count.
 */
final readonly class RecurrenceEnd {
	public const MAX_COUNT = 10_000;

	/**
	 * Create a never-ending definition bounded later by the generation window.
	 */
	public static function never(): self {
		return new self( null, null );
	}

	/**
	 * End after the inclusive local start date.
	 *
	 * @param string $date Canonical Y-m-d date.
	 * @throws InvalidArgumentException When the date is not canonical and valid.
	 */
	public static function on( string $date ): self {
		$canonical = trim( $date );

		if ( $canonical !== $date ) {
			throw new InvalidArgumentException( 'A recurrence end date must not contain surrounding whitespace.' );
		}

		$date = $canonical;
		$test = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );

		if ( false === $test || $test->format( 'Y-m-d' ) !== $date ) {
			throw new InvalidArgumentException( 'A recurrence end date must use a valid Y-m-d value.' );
		}

		return new self( $date, null );
	}

	/**
	 * End after exactly this many scheduled slots, including the first.
	 *
	 * @param int $count Positive bounded occurrence count.
	 * @throws InvalidArgumentException When the count is outside the supported range.
	 */
	public static function after( int $count ): self {
		if ( $count < 1 || $count > self::MAX_COUNT ) {
			throw new InvalidArgumentException( 'A recurrence count is outside the supported range.' );
		}

		return new self( null, $count );
	}

	/**
	 * Store one already validated termination condition.
	 *
	 * @param string|null $until_date Inclusive canonical end date.
	 * @param int|null    $count      Positive scheduled-slot count.
	 */
	private function __construct(
		private ?string $until_date,
		private ?int $count
	) {}

	/**
	 * Return the inclusive local end date, when configured.
	 */
	public function until_date(): ?string {
		return $this->until_date;
	}

	/**
	 * Return the maximum scheduled-slot count, when configured.
	 */
	public function count(): ?int {
		return $this->count;
	}
}
