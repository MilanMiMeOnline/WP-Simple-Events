<?php
/**
 * Bounded recurrence generation window.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Prevents a caller from requesting an unbounded or excessive projection.
 */
final readonly class RecurrenceGenerationWindow {
	public const DEFAULT_MAX_ROWS = 1_000;
	public const MAX_ROWS         = 1_000;
	public const MAX_SPAN_DAYS    = 550;

	/**
	 * Create one inclusive local-date window.
	 *
	 * @param string $from_date    Inclusive canonical start date.
	 * @param string $through_date Inclusive canonical end date.
	 * @param int    $max_rows     Positive output-row limit.
	 * @throws InvalidArgumentException When dates, horizon or row limit are invalid.
	 */
	public static function between(
		string $from_date,
		string $through_date,
		int $max_rows = self::DEFAULT_MAX_ROWS
	): self {
		$timezone = new DateTimeZone( 'UTC' );
		$from     = DateTimeImmutable::createFromFormat( '!Y-m-d', $from_date, $timezone );
		$through  = DateTimeImmutable::createFromFormat( '!Y-m-d', $through_date, $timezone );

		if ( false === $from || $from->format( 'Y-m-d' ) !== $from_date
			|| false === $through || $through->format( 'Y-m-d' ) !== $through_date
		) {
			throw new InvalidArgumentException( 'A recurrence generation window requires valid canonical dates.' );
		}

		$span = (int) $from->diff( $through )->format( '%r%a' );

		if ( $span < 0 || $span > self::MAX_SPAN_DAYS ) {
			throw new InvalidArgumentException( 'The recurrence generation window is outside its supported horizon.' );
		}

		if ( $max_rows < 1 || $max_rows > self::MAX_ROWS ) {
			throw new InvalidArgumentException( 'The recurrence generation row limit is outside its supported range.' );
		}

		return new self( $from_date, $through_date, $max_rows );
	}

	/**
	 * Store an already validated generation window.
	 *
	 * @param string $from_date    Inclusive canonical start date.
	 * @param string $through_date Inclusive canonical end date.
	 * @param int    $max_rows     Positive output-row limit.
	 */
	private function __construct(
		private string $from_date,
		private string $through_date,
		private int $max_rows
	) {}

	/**
	 * Return the inclusive canonical start date.
	 */
	public function from_date(): string {
		return $this->from_date;
	}

	/**
	 * Return the inclusive canonical end date.
	 */
	public function through_date(): string {
		return $this->through_date;
	}

	/**
	 * Return the maximum number of generated output rows.
	 */
	public function max_rows(): int {
		return $this->max_rows;
	}
}
