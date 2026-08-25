<?php
/**
 * One occurrence in a recurrence impact preview.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;

/**
 * Keeps preview output identity-bound and safe for a future REST serializer.
 */
final readonly class RecurrenceImpactItem {
	/**
	 * Create one changed or exception-affected preview item.
	 *
	 * @param string               $recurrence_id     Immutable identity.
	 * @param string               $public_key        Stable public key.
	 * @param array                $changes           Visible change types.
	 * @param bool                 $exception_affected Whether exception state changed.
	 * @param EventOccurrence|null $before            Current effective occurrence.
	 * @param EventOccurrence|null $after             Proposed effective occurrence.
	 * @phpstan-param list<RecurrenceImpactChange> $changes
	 * @throws InvalidArgumentException When the item contains no actual impact.
	 */
	public function __construct(
		public string $recurrence_id,
		public string $public_key,
		public array $changes,
		public bool $exception_affected,
		public ?EventOccurrence $before,
		public ?EventOccurrence $after
	) {
		if ( array() === $this->changes && ! $this->exception_affected ) {
			throw new InvalidArgumentException( 'A recurrence impact item must describe an actual change.' );
		}

		if ( null === $this->before && null === $this->after ) {
			throw new InvalidArgumentException( 'A recurrence impact item requires current or proposed state.' );
		}
	}

	/**
	 * Return the chronology key used by the editor preview.
	 *
	 * @throws InvalidArgumentException When neither current nor proposed state is available.
	 */
	public function sort_timestamp(): int {
		$occurrence = $this->after ?? $this->before;

		if ( null === $occurrence ) {
			throw new InvalidArgumentException( 'A recurrence impact item requires a chronology source.' );
		}

		return $occurrence->date_range->start_utc();
	}
}
