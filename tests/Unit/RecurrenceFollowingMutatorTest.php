<?php
/**
 * Tests for this-and-following schedule mutation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingMutator;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves that a future replacement has one stable, history-safe boundary.
 */
#[CoversClass( RecurrenceFollowingMutator::class )]
final class RecurrenceFollowingMutatorTest extends TestCase {
	private const SERIES_UID = 'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad';

	/**
	 * A new boundary preserves history and all sparse exception state.
	 */
	public function test_new_boundary_replaces_only_the_complete_future_schedule(): void {
		$target    = '2027-01-03T19:00:00';
		$exclusion = new OccurrenceExclusion( '2027-01-04T19:00:00', OccurrenceExclusionAction::CANCEL );
		$override  = OccurrenceOverride::from_fields( '2027-01-05T19:00:00', array( OccurrenceOverride::TITLE => 'Special' ) );
		$current   = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->segment( 0, '2027-01-01T19:00:00' ) ),
			array(),
			array( $exclusion ),
			array( $override )
		);

		$proposed = ( new RecurrenceFollowingMutator() )->replace_from(
			$current,
			$target,
			$this->range( '2027-01-03T20:00:00', '2027-01-03T22:00:00' ),
			RecurrenceRule::daily( 2 )
		);

		self::assertCount( 2, $proposed->segments );
		self::assertSame( $current->segments[0], $proposed->segments[0] );
		self::assertSame( 1, $proposed->segments[1]->id );
		self::assertSame( $target, $proposed->segments[1]->anchor );
		self::assertSame( '2027-01-03T20:00:00', $proposed->segments[1]->template->start_local() );
		self::assertSame( $current->exclusions, $proposed->exclusions );
		self::assertSame( $current->overrides, $proposed->overrides );
	}

	/**
	 * Re-editing an existing boundary retains its ID and removes later changes.
	 */
	public function test_existing_boundary_retains_id_and_removes_later_segments(): void {
		$target  = '2027-01-03T19:00:00';
		$current = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array(
				$this->segment( 0, '2027-01-01T19:00:00' ),
				$this->segment( 8, $target ),
				$this->segment( 20, '2027-01-05T19:00:00' ),
			)
		);

		$proposed = ( new RecurrenceFollowingMutator() )->replace_from(
			$current,
			$target,
			$this->range( '2027-01-03T21:00:00', '2027-01-03T23:00:00' ),
			RecurrenceRule::weekly( array( 7 ) )
		);

		self::assertSame( array( 0, 8 ), array_map( static fn ( ScheduleSegment $segment ): int => $segment->id, $proposed->segments ) );
		self::assertSame( '2027-01-03T21:00:00', $proposed->segments[1]->template->start_local() );
	}

	/**
	 * A fresh split never reuses an ID from a removed later segment.
	 */
	public function test_new_boundary_allocates_id_after_removed_future_ids(): void {
		$current = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array(
				$this->segment( 0, '2027-01-01T19:00:00' ),
				$this->segment( 8, '2027-01-03T19:00:00' ),
				$this->segment( 20, '2027-01-05T19:00:00' ),
			)
		);

		$proposed = ( new RecurrenceFollowingMutator() )->replace_from(
			$current,
			'2027-01-04T19:00:00',
			$this->range( '2027-01-04T19:00:00', '2027-01-04T21:00:00' ),
			RecurrenceRule::daily()
		);

		self::assertSame( array( 0, 8, 21 ), array_map( static fn ( ScheduleSegment $segment ): int => $segment->id, $proposed->segments ) );
	}

	/**
	 * The root is deliberately owned by complete-series editing.
	 */
	public function test_root_boundary_is_rejected(): void {
		$current = $this->daily();

		$this->expectException( InvalidArgumentException::class );
		( new RecurrenceFollowingMutator() )->replace_from(
			$current,
			'2027-01-01T19:00:00',
			$this->range( '2027-01-01T20:00:00', '2027-01-01T22:00:00' ),
			RecurrenceRule::daily()
		);
	}

	/**
	 * A date-shaped identity that is not generated cannot become a boundary.
	 */
	public function test_non_occurrence_boundary_is_rejected(): void {
		$current = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->segment( 0, '2027-01-01T19:00:00', RecurrenceRule::daily( 2 ) ) )
		);

		$this->expectException( InvalidArgumentException::class );
		( new RecurrenceFollowingMutator() )->replace_from(
			$current,
			'2027-01-02T19:00:00',
			$this->range( '2027-01-02T19:00:00', '2027-01-02T21:00:00' ),
			RecurrenceRule::daily()
		);
	}

	/**
	 * A replacement definition must generate its own selected first date.
	 */
	public function test_replacement_definition_must_include_its_seed(): void {
		$current = $this->daily();

		$this->expectException( InvalidArgumentException::class );
		( new RecurrenceFollowingMutator() )->replace_from(
			$current,
			'2027-01-03T19:00:00',
			$this->range( '2027-01-03T19:00:00', '2027-01-03T21:00:00' ),
			RecurrenceRule::weekly( array( 1 ) )
		);
	}

	/**
	 * Build a daily root aggregate.
	 */
	private function daily(): RecurrenceAggregate {
		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->segment( 0, '2027-01-01T19:00:00' ) )
		);
	}

	/**
	 * Build one schedule segment.
	 *
	 * @param int                 $id     Stable segment ID.
	 * @param string              $anchor Immutable segment anchor.
	 * @param RecurrenceRule|null $rule   Optional recurrence definition.
	 */
	private function segment( int $id, string $anchor, ?RecurrenceRule $rule = null ): ScheduleSegment {
		return new ScheduleSegment(
			$id,
			$anchor,
			$this->range( $anchor, substr( $anchor, 0, 10 ) . 'T21:00:00' ),
			$rule ?? RecurrenceRule::daily()
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
