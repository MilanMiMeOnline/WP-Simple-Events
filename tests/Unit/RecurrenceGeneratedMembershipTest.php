<?php
/**
 * Tests for generated occurrence membership.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGeneratedMembership;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves exact membership across segment boundaries and rule gaps.
 */
#[CoversClass( RecurrenceGeneratedMembership::class )]
final class RecurrenceGeneratedMembershipTest extends TestCase {
	private const SERIES_UID = 'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad';

	/**
	 * Anchors and generated future slots belong to their active segments.
	 */
	public function test_generated_slots_and_segment_anchors_belong(): void {
		$aggregate  = $this->aggregate();
		$membership = new RecurrenceGeneratedMembership();

		self::assertTrue( $membership->belongs( $aggregate, '2027-01-03T19:00:00' ) );
		self::assertTrue( $membership->belongs( $aggregate, '2027-01-05T19:00:00' ) );
		self::assertTrue( $membership->belongs( $aggregate, '2027-01-07T20:00:00' ) );
	}

	/**
	 * A valid date-shaped identity in a rule gap does not belong.
	 */
	public function test_rule_gap_does_not_belong(): void {
		self::assertFalse(
			( new RecurrenceGeneratedMembership() )->belongs( $this->aggregate(), '2027-01-06T20:00:00' )
		);
	}

	/**
	 * Manual identities are rejected rather than interpreted chronologically.
	 */
	public function test_manual_identity_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		( new RecurrenceGeneratedMembership() )->belongs(
			$this->aggregate(),
			'manual:019c1d83-1798-4fac-a66d-ae8d67c46320'
		);
	}

	/**
	 * Build a daily root followed by an every-other-day segment.
	 */
	private function aggregate(): RecurrenceAggregate {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$following = $this->range( '2027-01-05T20:00:00', '2027-01-05T22:00:00' );

		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array(
				new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily() ),
				new ScheduleSegment( 8, '2027-01-05T19:00:00', $following, RecurrenceRule::daily( 2 ) ),
			)
		);
	}

	/**
	 * Build one timed Brussels range.
	 *
	 * @param string $start Canonical local start.
	 * @param string $end   Canonical local end.
	 */
	private function range( string $start, string $end ): EventDateRange {
		return EventDateRange::from_local( $start, $end, false, 'Europe/Brussels' );
	}
}
