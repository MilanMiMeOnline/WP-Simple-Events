<?php
/**
 * Recurrence generation result.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Immutable complete output for one explicitly bounded window.
 */
final readonly class RecurrenceGenerationResult {
	/**
	 * Store one complete bounded result.
	 *
	 * @param RecurrenceSlot[] $slots            Chronologically ordered slots.
	 * @param string           $coverage_from    Inclusive canonical coverage start.
	 * @param string           $coverage_through Inclusive canonical coverage end.
	 */
	public function __construct(
		private array $slots,
		private string $coverage_from,
		private string $coverage_through
	) {}

	/**
	 * Return the complete chronological slot list.
	 *
	 * @return RecurrenceSlot[]
	 */
	public function slots(): array {
		return $this->slots;
	}

	/**
	 * Return the inclusive canonical coverage start.
	 */
	public function coverage_from(): string {
		return $this->coverage_from;
	}

	/**
	 * Return the inclusive canonical coverage end.
	 */
	public function coverage_through(): string {
		return $this->coverage_through;
	}
}
