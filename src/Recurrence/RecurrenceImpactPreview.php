<?php
/**
 * Complete recurrence impact preview.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Carries a bounded chronological set of changes for one explicit edit scope.
 */
final readonly class RecurrenceImpactPreview {
	/**
	 * Store one validated preview.
	 *
	 * @param RecurrenceEditScope $scope         Explicit selected scope.
	 * @param string|null         $target         Selected occurrence identity.
	 * @param array               $items          Chronological changed occurrences.
	 * @phpstan-param list<RecurrenceImpactItem> $items
	 */
	public function __construct(
		public RecurrenceEditScope $scope,
		public ?string $target,
		public array $items
	) {}

	/**
	 * Count items containing one visible change type.
	 *
	 * @param RecurrenceImpactChange $change Requested change.
	 */
	public function count( RecurrenceImpactChange $change ): int {
		return count(
			array_filter(
				$this->items,
				static fn ( RecurrenceImpactItem $item ): bool => in_array( $change, $item->changes, true )
			)
		);
	}

	/**
	 * Count identities whose manual, exclusion or override state changed.
	 */
	public function exception_affected_count(): int {
		return count(
			array_filter(
				$this->items,
				static fn ( RecurrenceImpactItem $item ): bool => $item->exception_affected
			)
		);
	}
}
