<?php
/**
 * In-memory recurrence aggregate store.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\ConcurrentRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateSnapshot;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateWriteStatus;

/**
 * Provides deterministic aggregate reads and writes for application tests.
 */
final class FakeRecurrenceAggregateStore implements ConcurrentRecurrenceAggregateStore {
	/**
	 * Aggregate currently stored by the fake.
	 *
	 * @var RecurrenceAggregate|null
	 */
	public ?RecurrenceAggregate $aggregate = null;

	/**
	 * Whether reads should emulate corrupt metadata.
	 *
	 * @var bool
	 */
	public bool $corrupt = false;

	/**
	 * Whether the conditional write should emulate an intervening editor.
	 *
	 * @var bool
	 */
	public bool $conflict = false;

	/**
	 * Number of canonical aggregate reads.
	 *
	 * @var int
	 */
	public int $load_calls = 0;

	/**
	 * Create the test store.
	 *
	 * @param bool $write_result Configured write result.
	 */
	public function __construct( private readonly bool $write_result = true ) {}

	/**
	 * Return configured state.
	 *
	 * @param int $event_id Ignored event ID.
	 * @throws InvalidArgumentException When corrupt state is configured.
	 */
	public function load( int $event_id ): ?RecurrenceAggregate {
		unset( $event_id );
		++$this->load_calls;

		if ( $this->corrupt ) {
			throw new InvalidArgumentException( 'Corrupt aggregate fixture.' );
		}

		return $this->aggregate;
	}

	/**
	 * Store configured state when writes succeed.
	 *
	 * @param int                 $event_id Ignored event ID.
	 * @param RecurrenceAggregate $aggregate Submitted aggregate.
	 */
	public function replace( int $event_id, RecurrenceAggregate $aggregate ): bool {
		unset( $event_id );

		if ( $this->write_result ) {
			$this->aggregate = $aggregate;
		}

		return $this->write_result;
	}

	/**
	 * Return current fake state and its canonical revision.
	 *
	 * @param int $event_id Ignored event ID.
	 * @throws InvalidArgumentException When corrupt state is configured.
	 */
	public function snapshot( int $event_id ): RecurrenceAggregateSnapshot {
		$aggregate = $this->load( $event_id );

		return new RecurrenceAggregateSnapshot(
			$aggregate,
			( new RecurrenceAggregateRevision() )->token( $aggregate )
		);
	}

	/**
	 * Conditionally store fake state.
	 *
	 * @param int                 $event_id         Ignored event ID.
	 * @param RecurrenceAggregate $aggregate        Submitted aggregate.
	 * @param string              $expected_revision Expected current revision.
	 */
	public function replace_if_current(
		int $event_id,
		RecurrenceAggregate $aggregate,
		string $expected_revision
	): RecurrenceAggregateWriteStatus {
		$current = $this->snapshot( $event_id );

		if ( $this->conflict || ! hash_equals( $current->revision, $expected_revision ) ) {
			return RecurrenceAggregateWriteStatus::CONFLICT;
		}

		if ( $aggregate == $current->aggregate ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Value objects need semantic equality.
			return RecurrenceAggregateWriteStatus::UNCHANGED;
		}

		if ( ! $this->write_result ) {
			return RecurrenceAggregateWriteStatus::FAILED;
		}

		$this->aggregate = $aggregate;

		return RecurrenceAggregateWriteStatus::STORED;
	}

	/**
	 * Conditionally remove fake state.
	 *
	 * @param int    $event_id         Ignored event ID.
	 * @param string $expected_revision Expected current revision.
	 */
	public function remove_if_current(
		int $event_id,
		string $expected_revision
	): RecurrenceAggregateWriteStatus {
		$current = $this->snapshot( $event_id );

		if ( $this->conflict || ! hash_equals( $current->revision, $expected_revision ) ) {
			return RecurrenceAggregateWriteStatus::CONFLICT;
		}

		if ( null === $current->aggregate ) {
			return RecurrenceAggregateWriteStatus::UNCHANGED;
		}

		if ( ! $this->write_result ) {
			return RecurrenceAggregateWriteStatus::FAILED;
		}

		$this->aggregate = null;

		return RecurrenceAggregateWriteStatus::STORED;
	}
}
