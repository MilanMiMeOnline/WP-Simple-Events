<?php
/**
 * Tests for complete recurring occurrence projection.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceSource;
use MiMe\WPSimpleEvents\Occurrence\RecurrenceOccurrenceBuilder;
use MiMe\WPSimpleEvents\Occurrence\RecurrenceOccurrenceProjector;
use MiMe\WPSimpleEvents\Recurrence\ManualOccurrence;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceProjectionStore;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves segments, exceptions, manual additions, bounds and fail-closed health.
 */
#[CoversClass( RecurrenceOccurrenceProjector::class )]
#[CoversClass( RecurrenceOccurrenceBuilder::class )]
final class RecurrenceOccurrenceProjectorTest extends TestCase {
	private const SERIES_UID = 'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad';
	private const MANUAL_ID  = 'manual:019c1d83-1798-4fac-a66d-ae8d67c46320';

	/**
	 * Reset derived-health metadata before each test.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Generated, skipped, cancelled, moved and manual state reconcile completely.
	 */
	public function test_complete_exceptions_and_manuals_are_projected(): void {
		update_post_meta( 42, EventMeta::INDEX_DIRTY, true );

		$root       = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$moved      = $this->range( '2027-01-04T20:00:00', '2027-01-04T22:00:00' );
		$manual     = $this->range( '2027-01-05T18:00:00', '2027-01-05T19:00:00' );
		$aggregate  = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily() ) ),
			array( new ManualOccurrence( self::MANUAL_ID, $manual ) ),
			array(
				new OccurrenceExclusion( '2027-01-02T19:00:00', OccurrenceExclusionAction::SKIP ),
				new OccurrenceExclusion( '2027-01-03T19:00:00', OccurrenceExclusionAction::CANCEL ),
			),
			array(
				OccurrenceOverride::from_fields(
					'2027-01-03T19:00:00',
					array( OccurrenceOverride::STATUS => EventStatus::POSTPONED )
				),
				OccurrenceOverride::from_fields(
					'2027-01-04T19:00:00',
					array(
						OccurrenceOverride::DATE_RANGE => $moved,
						OccurrenceOverride::STATUS     => EventStatus::POSTPONED,
					)
				),
			)
		);
		$store      = new FakeOccurrenceProjectionStore();
		$projected  = ( new RecurrenceOccurrenceProjector( $store ) )->project(
			42,
			$aggregate,
			EventStatus::SCHEDULED,
			RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-05', 10 )
		);
		$identities = array_map( static fn ( EventOccurrence $row ): string => $row->identity->recurrence_id(), $store->occurrences );

		self::assertTrue( $projected );
		self::assertSame(
			array( '2027-01-01T19:00:00', '2027-01-03T19:00:00', '2027-01-04T19:00:00', self::MANUAL_ID, '2027-01-05T19:00:00' ),
			$identities
		);
		self::assertSame( EventStatus::CANCELLED, $store->occurrences[1]->status );
		self::assertSame( EventStatus::POSTPONED, $store->occurrences[2]->status );
		self::assertSame( '2027-01-04T20:00:00', $store->occurrences[2]->date_range->start_local() );
		self::assertSame( OccurrenceSource::MANUAL, $store->occurrences[3]->source );
		self::assertSame( '2027-01-01', $store->coverage?->from_date() );
		self::assertSame( '2027-01-05', $store->coverage?->through_date() );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * This-and-following segments keep the selected original identity after a move.
	 */
	public function test_segment_boundary_preserves_anchor_and_replaces_following_schedule(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$following = $this->range( '2027-01-05T20:00:00', '2027-01-05T22:00:00' );
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array(
				new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily() ),
				new ScheduleSegment( 8, '2027-01-05T19:00:00', $following, RecurrenceRule::daily( 2 ) ),
			)
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertTrue(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-09', 20 )
			)
		);

		self::assertSame(
			array(
				'2027-01-01T19:00:00',
				'2027-01-02T19:00:00',
				'2027-01-03T19:00:00',
				'2027-01-04T19:00:00',
				'2027-01-05T19:00:00',
				'2027-01-07T20:00:00',
				'2027-01-09T20:00:00',
			),
			array_map( static fn ( EventOccurrence $row ): string => $row->identity->recurrence_id(), $store->occurrences )
		);
		self::assertSame( '2027-01-05T20:00:00', $store->occurrences[4]->date_range->start_local() );
		self::assertSame( 8, $store->occurrences[4]->segment_id );
	}

	/**
	 * An earlier effective segment seed cannot regenerate identities owned by its predecessor.
	 */
	public function test_earlier_segment_seed_keeps_generated_identities_unique(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$following = $this->range( '2027-01-03T20:00:00', '2027-01-03T22:00:00' );
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array(
				new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily() ),
				new ScheduleSegment( 8, '2027-01-05T19:00:00', $following, RecurrenceRule::daily() ),
			)
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertTrue(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-08', 20 )
			)
		);
		self::assertSame(
			array(
				'2027-01-01T19:00:00',
				'2027-01-02T19:00:00',
				'2027-01-03T19:00:00',
				'2027-01-05T19:00:00',
				'2027-01-04T19:00:00',
				'2027-01-06T20:00:00',
				'2027-01-07T20:00:00',
				'2027-01-08T20:00:00',
			),
			array_map( static fn ( EventOccurrence $row ): string => $row->identity->recurrence_id(), $store->occurrences )
		);
		self::assertSame( '2027-01-03T20:00:00', $store->occurrences[3]->date_range->start_local() );
	}

	/**
	 * A segment cannot claim an anchor that its preceding schedule never generated.
	 */
	public function test_orphaned_segment_anchor_fails_closed(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$following = $this->range( '2027-01-02T20:00:00', '2027-01-02T22:00:00' );
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array(
				new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily( 2 ) ),
				new ScheduleSegment( 8, '2027-01-02T19:00:00', $following, RecurrenceRule::daily() ),
			)
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertFalse(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-08', 20 )
			)
		);
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * A generated occurrence moved into the window remains projected and validated.
	 */
	public function test_date_override_moved_into_window_is_projected(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$moved     = $this->range( '2027-01-06T10:00:00', '2027-01-06T12:00:00' );
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily() ) ),
			array(),
			array(),
			array(
				OccurrenceOverride::from_fields(
					'2027-01-02T19:00:00',
					array( OccurrenceOverride::DATE_RANGE => $moved )
				),
			)
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertTrue(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-01-05', '2027-01-07', 10 )
			)
		);
		self::assertSame(
			array(
				'2027-01-05T19:00:00',
				'2027-01-02T19:00:00',
				'2027-01-06T19:00:00',
				'2027-01-07T19:00:00',
			),
			array_map( static fn ( EventOccurrence $row ): string => $row->identity->recurrence_id(), $store->occurrences )
		);
		self::assertSame( '2027-01-06T10:00:00', $store->occurrences[1]->date_range->start_local() );
	}

	/**
	 * A moved-in identity must still belong to its original bounded schedule.
	 */
	public function test_orphaned_date_override_moved_into_window_fails_closed(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$moved     = $this->range( '2027-02-02T10:00:00', '2027-02-02T12:00:00' );
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily( 3 ) ) ),
			array(),
			array(),
			array(
				OccurrenceOverride::from_fields(
					'2027-01-02T19:00:00',
					array( OccurrenceOverride::DATE_RANGE => $moved )
				),
			)
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertFalse(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-02-01', '2027-02-03', 10 )
			)
		);
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * A detached modified occurrence keeps its generated identity and projects manually.
	 */
	public function test_detached_generated_occurrence_preserves_identity(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$detached  = $this->range( '2027-01-02T19:00:00', '2027-01-02T21:00:00' );
		$identity  = $detached->start_local();
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily( 3 ) ) ),
			array( new ManualOccurrence( $identity, $detached, EventStatus::POSTPONED ) ),
			array(),
			array(
				OccurrenceOverride::from_fields(
					$identity,
					array( OccurrenceOverride::TITLE => 'Retained workshop' )
				),
			)
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertTrue(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-04', 10 )
			)
		);
		self::assertSame( $identity, $store->occurrences[1]->identity->recurrence_id() );
		self::assertSame( OccurrenceSource::MANUAL, $store->occurrences[1]->source );
		self::assertSame( EventStatus::POSTPONED, $store->occurrences[1]->status );
	}

	/**
	 * A detached cancellation remains visible and reversible under the same identity.
	 */
	public function test_detached_generated_cancellation_remains_cancelled(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$detached  = $this->range( '2027-01-02T19:00:00', '2027-01-02T21:00:00' );
		$identity  = $detached->start_local();
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily( 3 ) ) ),
			array( new ManualOccurrence( $identity, $detached ) ),
			array( new OccurrenceExclusion( $identity, OccurrenceExclusionAction::CANCEL ) )
		);
		$rows      = ( new RecurrenceOccurrenceBuilder() )->build(
			42,
			$aggregate,
			EventStatus::SCHEDULED,
			RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-03', 10 ),
			1
		);

		self::assertSame( $identity, $rows[1]->identity->recurrence_id() );
		self::assertSame( OccurrenceSource::MANUAL, $rows[1]->source );
		self::assertSame( EventStatus::CANCELLED, $rows[1]->status );
	}

	/**
	 * A detached skip stays absent until its exclusion is explicitly restored.
	 */
	public function test_detached_generated_skip_remains_absent(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$detached  = $this->range( '2027-01-02T19:00:00', '2027-01-02T21:00:00' );
		$identity  = $detached->start_local();
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily( 3 ) ) ),
			array( new ManualOccurrence( $identity, $detached ) ),
			array( new OccurrenceExclusion( $identity, OccurrenceExclusionAction::SKIP ) )
		);
		$rows      = ( new RecurrenceOccurrenceBuilder() )->build(
			42,
			$aggregate,
			EventStatus::SCHEDULED,
			RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-03', 10 ),
			1
		);

		self::assertSame(
			array( '2027-01-01T19:00:00' ),
			array_map( static fn ( EventOccurrence $row ): string => $row->identity->recurrence_id(), $rows )
		);
	}

	/**
	 * A complete window may legitimately activate with zero occurrence rows.
	 */
	public function test_empty_complete_window_is_successful(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::yearly_on( 1, 1 ) ) )
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertTrue(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-02-01', '2027-02-10', 10 )
			)
		);
		self::assertSame( array(), $store->occurrences );
	}

	/**
	 * A segment definition that skips its template seed fails the complete build.
	 */
	public function test_definition_that_skips_segment_seed_fails_closed(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::weekly( array( 1 ) ) ) )
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertFalse(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-10', 10 )
			)
		);
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
		self::assertSame( array(), $store->occurrences );
	}

	/**
	 * An in-window exception for a date absent from the schedule cannot be orphaned.
	 */
	public function test_orphaned_in_window_exception_fails_closed(): void {
		$root      = $this->range( '2027-01-01T19:00:00', '2027-01-01T21:00:00' );
		$aggregate = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily( 3 ) ) ),
			array(),
			array( new OccurrenceExclusion( '2027-01-02T19:00:00', OccurrenceExclusionAction::CANCEL ) )
		);
		$store     = new FakeOccurrenceProjectionStore();

		self::assertFalse(
			( new RecurrenceOccurrenceProjector( $store ) )->project(
				42,
				$aggregate,
				EventStatus::SCHEDULED,
				RecurrenceGenerationWindow::between( '2027-01-01', '2027-01-10', 10 )
			)
		);
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * Return one canonical timed range in the series timezone.
	 *
	 * @param string $start Canonical local start.
	 * @param string $end   Canonical local end.
	 */
	private function range( string $start, string $end ): EventDateRange {
		return EventDateRange::from_local( $start, $end, false, 'Europe/Brussels' );
	}
}
