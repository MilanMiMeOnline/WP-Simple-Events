<?php
/**
 * Tests for recurrence optimistic concurrency tokens.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateSnapshot;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves deterministic, exact and non-secret aggregate revisions.
 */
#[CoversClass( RecurrenceAggregateRevision::class )]
#[CoversClass( RecurrenceAggregateSnapshot::class )]
final class RecurrenceAggregateRevisionTest extends TestCase {
	/**
	 * Equivalent state has one token and a changed rule has another.
	 */
	public function test_tokens_are_deterministic_and_change_with_canonical_state(): void {
		$revisions = new RecurrenceAggregateRevision();
		$daily     = $this->aggregate();

		self::assertSame( $revisions->token( $daily ), $revisions->token( $this->aggregate() ) );
		self::assertNotSame( $revisions->token( $daily ), $revisions->token( $this->aggregate( 2 ) ) );
		self::assertNotSame( $revisions->token( null ), $revisions->token( $daily ) );
		self::assertTrue( $revisions->valid( $revisions->token( null ) ) );
		self::assertFalse( $revisions->valid( 'not-a-token' ) );
	}

	/**
	 * Snapshot construction rejects weak or malformed tokens.
	 */
	public function test_snapshot_rejects_invalid_revision(): void {
		$this->expectException( InvalidArgumentException::class );

		new RecurrenceAggregateSnapshot( null, '123' );
	}

	/**
	 * Return one minimal aggregate.
	 *
	 * @param int $interval Daily rule interval.
	 */
	private function aggregate( int $interval = 1 ): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04', null, true, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			'019c1d83-1798-4fac-a66d-ae8d67c46319',
			'Europe/Brussels',
			array( new ScheduleSegment( 0, '2027-01-04', $range, RecurrenceRule::daily( $interval ) ) )
		);
	}
}
