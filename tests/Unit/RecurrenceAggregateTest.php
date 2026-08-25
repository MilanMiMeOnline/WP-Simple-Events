<?php
/**
 * Tests for the complete recurrence aggregate.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\ManualOccurrence;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves aggregate identity, chronology, timezone and relationship invariants.
 */
#[CoversClass( RecurrenceAggregate::class )]
#[CoversClass( ScheduleSegment::class )]
#[CoversClass( ManualOccurrence::class )]
#[CoversClass( OccurrenceExclusion::class )]
final class RecurrenceAggregateTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';
	private const MANUAL_ID  = 'manual:019c1d83-1798-4fac-a66d-ae8d67c46320';

	/**
	 * A complete aggregate keeps stable identities through segments and exceptions.
	 */
	public function test_complete_aggregate_is_accepted(): void {
		$segments  = array(
			$this->segment( 0, '2027-01-04T19:00:00', '2027-01-04T19:00' ),
			$this->segment( 8, '2027-03-01T19:00:00', '2027-03-01T20:00' ),
		);
		$manual    = new ManualOccurrence(
			self::MANUAL_ID,
			$this->range( '2027-02-14T18:00' ),
			EventStatus::SCHEDULED
		);
		$cancel    = new OccurrenceExclusion(
			'2027-02-01T19:00:00',
			OccurrenceExclusionAction::CANCEL
		);
		$override  = OccurrenceOverride::from_fields(
			'2027-02-01T19:00:00',
			array( OccurrenceOverride::TITLE => 'Cancelled workshop' )
		);
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			$segments,
			array( $manual ),
			array( $cancel ),
			array( $override )
		);

		self::assertSame( self::SERIES_UID, $aggregate->series_uid );
		self::assertSame( array( 0, 8 ), array_map( static fn ( ScheduleSegment $segment ): int => $segment->id, $aggregate->segments ) );
		self::assertSame( self::MANUAL_ID, $aggregate->manuals[0]->recurrence_id );
		self::assertSame( OccurrenceExclusionAction::CANCEL, $aggregate->exclusions[0]->action );
	}

	/**
	 * Broad edits may detach a modified generated occurrence without changing its identity.
	 */
	public function test_detached_generated_occurrence_keeps_original_identity(): void {
		$identity  = '2027-02-01T19:00:00';
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->segment( 0, '2027-01-04T19:00:00', '2027-01-04T19:00' ) ),
			array( new ManualOccurrence( $identity, $this->range( '2027-02-01T19:00' ) ) )
		);

		self::assertSame( $identity, $aggregate->manuals[0]->recurrence_id );
	}

	/**
	 * The broad relationships reject ambiguous, orphaned or contradictory state.
	 */
	public function test_aggregate_relationship_conflicts_are_rejected(): void {
		$skip     = new OccurrenceExclusion( '2027-01-11T19:00:00', OccurrenceExclusionAction::SKIP );
		$override = OccurrenceOverride::from_fields(
			'2027-01-11T19:00:00',
			array( OccurrenceOverride::TITLE => 'Invisible title' )
		);

		$this->expectException( InvalidArgumentException::class );

		RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->segment( 0, '2027-01-04T19:00:00', '2027-01-04T19:00' ) ),
			array(),
			array( $skip ),
			array( $override )
		);
	}

	/**
	 * A manual override cannot invent a manual occurrence implicitly.
	 */
	public function test_orphaned_manual_override_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->segment( 0, '2027-01-04T19:00:00', '2027-01-04T19:00' ) ),
			array(),
			array(),
			array(
				OccurrenceOverride::from_fields(
					self::MANUAL_ID,
					array( OccurrenceOverride::VENUE => 'Side room' )
				),
			)
		);
	}

	/**
	 * Segment anchors are stable, unique and chronological rather than reorderable dates.
	 */
	public function test_duplicate_or_reverse_segment_anchor_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array(
				$this->segment( 0, '2027-01-04T19:00:00', '2027-01-04T19:00' ),
				$this->segment( 2, '2027-01-01T19:00:00', '2027-03-01T20:00' ),
			)
		);
	}

	/**
	 * Every effective date range remains in the one captured series timezone.
	 */
	public function test_manual_timezone_drift_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->segment( 0, '2027-01-04T19:00:00', '2027-01-04T19:00' ) ),
			array(
				new ManualOccurrence(
					self::MANUAL_ID,
					EventDateRange::from_local( '2027-02-14T18:00', '2027-02-14T20:00', false, 'UTC' )
				),
			)
		);
	}

	/**
	 * The aggregate is always rooted by segment zero at its own template start.
	 */
	public function test_invalid_root_segment_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->segment( 5, '2027-01-04T19:00:00', '2027-01-04T19:00' ) )
		);
	}

	/**
	 * Aggregate identity values must already be canonical and list sizes stay bounded.
	 */
	public function test_noncanonical_series_uid_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceAggregate::create(
			strtoupper( self::SERIES_UID ),
			'Europe/Brussels',
			array( $this->segment( 0, '2027-01-04T19:00:00', '2027-01-04T19:00' ) )
		);
	}

	/**
	 * Return one weekly segment with independent immutable anchor and effective start.
	 *
	 * @param int    $id              Stable segment ID.
	 * @param string $anchor          Original recurrence identity.
	 * @param string $effective_start Effective local start.
	 */
	private function segment( int $id, string $anchor, string $effective_start ): ScheduleSegment {
		return new ScheduleSegment(
			$id,
			$anchor,
			$this->range( $effective_start ),
			RecurrenceRule::weekly( array( 1 ) )
		);
	}

	/**
	 * Return one two-hour timed range in the aggregate timezone.
	 *
	 * @param string $start Effective local start.
	 */
	private function range( string $start ): EventDateRange {
		$end = substr( $start, 0, 11 ) . sprintf( '%02d:00', (int) substr( $start, 11, 2 ) + 2 );

		return EventDateRange::from_local( $start, $end, false, 'Europe/Brussels' );
	}
}
