<?php
/**
 * Explicit recurring projection coverage.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Carries only the inclusive local dates represented by one complete build.
 */
final readonly class OccurrenceProjectionCoverage {
	/**
	 * Build coverage from an already validated recurrence window.
	 *
	 * @param RecurrenceGenerationWindow $window Complete generation window.
	 */
	public static function from_window( RecurrenceGenerationWindow $window ): self {
		return new self( $window->from_date(), $window->through_date() );
	}

	/**
	 * Store validated inclusive boundaries.
	 *
	 * @param string $from_date    Inclusive canonical start date.
	 * @param string $through_date Inclusive canonical end date.
	 * @throws InvalidArgumentException When the end precedes the start.
	 */
	private function __construct(
		private string $from_date,
		private string $through_date
	) {
		if ( $this->from_date > $this->through_date ) {
			throw new InvalidArgumentException( 'Occurrence coverage cannot end before it starts.' );
		}
	}

	/** Return the inclusive canonical start date. */
	public function from_date(): string {
		return $this->from_date;
	}

	/** Return the inclusive canonical end date. */
	public function through_date(): string {
		return $this->through_date;
	}
}
