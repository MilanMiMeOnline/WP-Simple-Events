<?php
/**
 * Generated recurrence slot.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use MiMe\WPSimpleEvents\Domain\EventDateRange;

/**
 * Couples immutable original recurrence identity to its effective local range.
 */
final readonly class RecurrenceSlot {
	/**
	 * Store one validated generated slot.
	 *
	 * @param string         $recurrence_id Immutable original local slot.
	 * @param EventDateRange $date_range    Effective validated local range.
	 */
	public function __construct(
		private string $recurrence_id,
		private EventDateRange $date_range
	) {}

	/**
	 * Return the immutable original local slot.
	 */
	public function recurrence_id(): string {
		return $this->recurrence_id;
	}

	/**
	 * Return the effective validated date range.
	 */
	public function date_range(): EventDateRange {
		return $this->date_range;
	}
}
