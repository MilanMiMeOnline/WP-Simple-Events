<?php
/**
 * Tests for recurrence edit impact previews.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Recurrence\ManualOccurrence;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEditScope;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactChange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactItem;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactPreview;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactPreviewer;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves scope safety, stable identity and complete bounded impact counts.
 */
#[CoversClass( RecurrenceImpactPreviewer::class )]
#[CoversClass( RecurrenceImpactPreview::class )]
#[CoversClass( RecurrenceImpactItem::class )]
final class RecurrenceImpactPreviewerTest extends TestCase {
	private const SERIES_UID = 'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad';

	/**
	 * Identical complete aggregates have no impact.
	 */
	public function test_unchanged_complete_series_has_empty_impact(): void {
		$aggregate = $this->daily();
		$preview   = $this->preview( $aggregate, $aggregate, RecurrenceEditScope::COMPLETE_SERIES );

		self::assertSame( array(), $preview->items );
		self::assertSame( 0, $preview->exception_affected_count() );
		self::assertSame( 0, $preview->count( RecurrenceImpactChange::REMOVED ) );
	}

	/**
	 * Broad schedule edits report every removed occurrence in the bounded horizon.
	 */
	public function test_complete_series_reports_removed_occurrences(): void {
		$preview = $this->preview(
			$this->daily(),
			$this->daily( 2 ),
			RecurrenceEditScope::COMPLETE_SERIES
		);

		self::assertSame( 2, $preview->count( RecurrenceImpactChange::REMOVED ) );
		self::assertSame(
			array( '2027-01-02T19:00:00', '2027-01-04T19:00:00' ),
			array_map( static fn ( RecurrenceImpactItem $item ): string => $item->recurrence_id, $preview->items )
		);
	}

	/**
	 * Moving and postponing one occurrence remains bound to its original identity.
	 */
	public function test_only_this_reports_move_and_status_without_identity_churn(): void {
		$target   = '2027-01-03T19:00:00';
		$proposed = $this->daily(
			1,
			array(),
			array(
				OccurrenceOverride::from_fields(
					$target,
					array(
						OccurrenceOverride::DATE_RANGE => $this->range( '2027-01-03T20:00:00', '2027-01-03T22:00:00' ),
						OccurrenceOverride::STATUS     => EventStatus::POSTPONED,
					)
				),
			)
		);
		$preview  = $this->preview( $this->daily(), $proposed, RecurrenceEditScope::ONLY_THIS, $target );

		self::assertCount( 1, $preview->items );
		self::assertSame( $target, $preview->items[0]->recurrence_id );
		self::assertSame( OccurrenceIdentity::from( self::SERIES_UID, $target )->public_key(), $preview->items[0]->public_key );
		self::assertSame(
			array( RecurrenceImpactChange::MOVED, RecurrenceImpactChange::STATUS_CHANGED ),
			$preview->items[0]->changes
		);
		self::assertTrue( $preview->items[0]->exception_affected );
	}

	/**
	 * A cancellation is a readable status change rather than a removed occurrence.
	 */
	public function test_only_this_cancellation_reports_status_change(): void {
		$target   = '2027-01-03T19:00:00';
		$proposed = $this->daily(
			1,
			array( new OccurrenceExclusion( $target, OccurrenceExclusionAction::CANCEL ) )
		);
		$preview  = $this->preview( $this->daily(), $proposed, RecurrenceEditScope::ONLY_THIS, $target );

		self::assertSame( 1, $preview->count( RecurrenceImpactChange::STATUS_CHANGED ) );
		self::assertSame( 0, $preview->count( RecurrenceImpactChange::REMOVED ) );
		self::assertSame( EventStatus::CANCELLED, $preview->items[0]->after?->status );
	}

	/**
	 * An orphaned modified slot becomes manual without breaking its public URL.
	 */
	public function test_detached_generated_occurrence_reports_source_change_with_stable_key(): void {
		$target   = '2027-01-02T19:00:00';
		$override = OccurrenceOverride::from_fields( $target, array( OccurrenceOverride::TITLE => 'Special edition' ) );
		$current  = $this->daily( 1, array(), array( $override ) );
		$proposed = $this->daily(
			3,
			array(),
			array( $override ),
			array( new ManualOccurrence( $target, $this->range( $target, '2027-01-02T21:00:00' ) ) )
		);
		$preview  = $this->preview( $current, $proposed, RecurrenceEditScope::COMPLETE_SERIES );
		$detached = array_values(
			array_filter(
				$preview->items,
				static fn ( RecurrenceImpactItem $item ): bool => $target === $item->recurrence_id
			)
		)[0];

		self::assertSame( array( RecurrenceImpactChange::SOURCE_CHANGED ), $detached->changes );
		self::assertSame( $detached->before?->identity->public_key(), $detached->after?->identity->public_key() );
		self::assertTrue( $detached->exception_affected );
	}

	/**
	 * A following-scope segment may change the target and future without touching the past.
	 */
	public function test_this_and_following_accepts_new_segment_at_target(): void {
		$target   = '2027-01-03T19:00:00';
		$root     = new ScheduleSegment( 0, '2027-01-01T19:00:00', $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' ), RecurrenceRule::daily() );
		$future   = new ScheduleSegment( 8, $target, $this->range( '2027-01-03T20:00:00', '2027-01-03T22:00:00' ), RecurrenceRule::daily( 2 ) );
		$proposed = RecurrenceAggregate::create( self::SERIES_UID, 'Europe/Brussels', array( $root, $future ) );
		$preview  = $this->preview( $this->daily(), $proposed, RecurrenceEditScope::THIS_AND_FOLLOWING, $target );

		self::assertNotEmpty( $preview->items );
		self::assertSame( $target, $preview->items[0]->recurrence_id );
		self::assertSame( array( RecurrenceImpactChange::MOVED ), $preview->items[0]->changes );
		self::assertSame(
			array(),
			array_filter(
				$preview->items,
				static fn ( RecurrenceImpactItem $item ): bool => OccurrenceIdentity::is_generated_recurrence_id( $item->recurrence_id )
					&& strcmp( $item->recurrence_id, $target ) < 0
			)
		);
	}

	/**
	 * Only-this cannot silently rewrite a schedule.
	 */
	public function test_only_this_rejects_schedule_change(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->preview( $this->daily(), $this->daily( 2 ), RecurrenceEditScope::ONLY_THIS, '2027-01-03T19:00:00' );
	}

	/**
	 * This-and-following cannot mutate a segment before its selected boundary.
	 */
	public function test_this_and_following_rejects_prior_segment_change(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->preview( $this->daily(), $this->daily( 2 ), RecurrenceEditScope::THIS_AND_FOLLOWING, '2027-01-03T19:00:00' );
	}

	/**
	 * Preview input cannot change immutable series identity.
	 */
	public function test_preview_rejects_series_identity_change(): void {
		$proposed = RecurrenceAggregate::create(
			'019c1d83-1798-4fac-a66d-ae8d67c46319',
			'Europe/Brussels',
			array( $this->root_segment() )
		);

		$this->expectException( InvalidArgumentException::class );
		$this->preview( $this->daily(), $proposed, RecurrenceEditScope::COMPLETE_SERIES );
	}

	/**
	 * Build a daily aggregate with optional complete exception state.
	 *
	 * @param int   $interval   Daily interval.
	 * @param array $exclusions Complete exclusions.
	 * @param array $overrides  Complete overrides.
	 * @param array $manuals    Complete manual additions.
	 * @phpstan-param list<OccurrenceExclusion> $exclusions
	 * @phpstan-param list<OccurrenceOverride> $overrides
	 * @phpstan-param list<ManualOccurrence> $manuals
	 */
	private function daily(
		int $interval = 1,
		array $exclusions = array(),
		array $overrides = array(),
		array $manuals = array()
	): RecurrenceAggregate {
		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( $this->root_segment( $interval ) ),
			$manuals,
			$exclusions,
			$overrides
		);
	}

	/**
	 * Build one root schedule.
	 *
	 * @param int $interval Daily interval.
	 */
	private function root_segment( int $interval = 1 ): ScheduleSegment {
		return new ScheduleSegment(
			0,
			'2027-01-01T19:00:00',
			$this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' ),
			RecurrenceRule::daily( $interval )
		);
	}

	/**
	 * Compare aggregates inside the shared five-day test horizon.
	 *
	 * @param RecurrenceAggregate $current  Current aggregate.
	 * @param RecurrenceAggregate $proposed Proposed aggregate.
	 * @param RecurrenceEditScope $scope    Explicit mutation scope.
	 * @param string|null         $target   Optional target identity.
	 */
	private function preview(
		RecurrenceAggregate $current,
		RecurrenceAggregate $proposed,
		RecurrenceEditScope $scope,
		?string $target = null
	): RecurrenceImpactPreview {
		return ( new RecurrenceImpactPreviewer() )->preview(
			$current,
			$proposed,
			EventStatus::SCHEDULED,
			RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-05', 20 ),
			$scope,
			$target
		);
	}

	/**
	 * Build one canonical timed range.
	 *
	 * @param string $start Start local datetime.
	 * @param string $end   End local datetime.
	 */
	private function range( string $start, string $end ): EventDateRange {
		return EventDateRange::from_local( $start, $end, false, 'Europe/Brussels' );
	}
}
