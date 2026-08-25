<?php
/**
 * Production occurrence projection-window policy.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Centralizes fresh, renewal and minimum public coverage horizons.
 */
final readonly class OccurrenceProjectionWindowPolicy {
	public const FRESH_DAYS          = 540;
	public const RENEWAL_AFTER_DAYS  = 450;
	public const MINIMUM_PUBLIC_DAYS = 365;

	/**
	 * Build a fresh bounded production window.
	 *
	 * @param string $today Canonical WordPress-local current date.
	 */
	public function fresh_window( string $today ): RecurrenceGenerationWindow {
		return RecurrenceGenerationWindow::between(
			$this->date( $today )->format( 'Y-m-d' ),
			$this->date_after( $today, self::FRESH_DAYS ),
			RecurrenceGenerationWindow::MAX_ROWS
		);
	}

	/**
	 * Return the inclusive end required by the public readiness gate.
	 *
	 * @param string $today Canonical WordPress-local current date.
	 */
	public function minimum_through( string $today ): string {
		return $this->date_after( $today, self::MINIMUM_PUBLIC_DAYS );
	}

	/**
	 * Return the threshold below which buffered background renewal begins.
	 *
	 * @param string $today Canonical WordPress-local current date.
	 */
	public function renewal_through( string $today ): string {
		return $this->date_after( $today, self::RENEWAL_AFTER_DAYS );
	}

	/**
	 * Determine whether stored coverage is sufficient for public reads.
	 *
	 * @param string $from    Inclusive stored coverage start.
	 * @param string $through Inclusive stored coverage end.
	 * @param string $today   Canonical WordPress-local current date.
	 */
	public function supports_public_reads( string $from, string $through, string $today ): bool {
		return $this->canonical( $from )
			&& $this->canonical( $through )
			&& $from <= $today
			&& $through >= $this->minimum_through( $today );
	}

	/**
	 * Determine whether buffered maintenance should rebuild the projection.
	 *
	 * @param string $from    Inclusive stored coverage start.
	 * @param string $through Inclusive stored coverage end.
	 * @param string $today   Canonical WordPress-local current date.
	 */
	public function needs_renewal( string $from, string $through, string $today ): bool {
		return ! $this->canonical( $from )
			|| ! $this->canonical( $through )
			|| $from > $today
			|| $through < $this->renewal_through( $today );
	}

	/**
	 * Return whether a value is one exact canonical date.
	 *
	 * @param string $value Candidate date.
	 */
	private function canonical( string $value ): bool {
		try {
			$this->date( $value );
		} catch ( InvalidArgumentException ) {
			return false;
		}

		return true;
	}

	/**
	 * Add calendar days independently from the machine timezone.
	 *
	 * @param string $date Canonical base date.
	 * @param int    $days Positive calendar days to add.
	 */
	private function date_after( string $date, int $days ): string {
		return $this->date( $date )->modify( '+' . $days . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Parse one canonical date.
	 *
	 * @param string $value Candidate canonical date.
	 * @throws InvalidArgumentException When the date is not canonical.
	 */
	private function date( string $value ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			throw new InvalidArgumentException( 'Occurrence projection policy requires a canonical date.' );
		}

		return $date;
	}
}
