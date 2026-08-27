<?php
/**
 * Production occurrence repair window.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use RuntimeException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Builds the same bounded future horizon used by the recurrence editor.
 */
final readonly class OccurrenceRepairWindowFactory implements OccurrenceProjectionWindowFactory {
	/**
	 * Create the production window factory.
	 *
	 * @param OccurrenceProjectionWindowPolicy $policy Projection horizon policy.
	 */
	public function __construct(
		private OccurrenceProjectionWindowPolicy $policy = new OccurrenceProjectionWindowPolicy()
	) {}

	/**
	 * Build a production window from a canonical WordPress-local date.
	 *
	 * @param string $from_date Current WordPress-local date.
	 * @throws InvalidArgumentException When the supplied date is not canonical.
	 */
	public function from_date( string $from_date ): RecurrenceGenerationWindow {
		try {
			return $this->policy->fresh_window( $from_date );
		} catch ( InvalidArgumentException ) {
			throw new InvalidArgumentException( 'Occurrence repair requires a canonical local date.' );
		}
	}

	/**
	 * Build the current production window from WordPress' local clock.
	 *
	 * @throws RuntimeException When WordPress cannot format its current local date.
	 */
	public function current(): RecurrenceGenerationWindow {
		$today = wp_date( 'Y-m-d' );

		if ( ! is_string( $today ) ) {
			throw new RuntimeException( 'WordPress could not provide the current local date.' );
		}

		return $this->from_date( $today );
	}
}
