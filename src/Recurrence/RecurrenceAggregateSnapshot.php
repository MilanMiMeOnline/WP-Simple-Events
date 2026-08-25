<?php
/**
 * Versioned canonical recurrence snapshot.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;

/**
 * Binds one aggregate state to its optimistic concurrency token.
 */
final readonly class RecurrenceAggregateSnapshot {
	/**
	 * Create one validated snapshot.
	 *
	 * @param RecurrenceAggregate|null $aggregate Current aggregate or one-off state.
	 * @param string                   $revision  Canonical revision token.
	 * @throws InvalidArgumentException When the token is not canonical.
	 */
	public function __construct(
		public ?RecurrenceAggregate $aggregate,
		public string $revision
	) {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $this->revision ) ) {
			throw new InvalidArgumentException( 'A recurrence snapshot requires one canonical revision token.' );
		}
	}
}
