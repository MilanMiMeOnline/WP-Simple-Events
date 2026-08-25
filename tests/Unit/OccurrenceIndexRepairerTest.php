<?php
/**
 * Tests for type-aware occurrence index repair.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\EventPublicationPolicy;
use MiMe\WPSimpleEvents\Application\EventValidator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceRepairWindowFactory;
use MiMe\WPSimpleEvents\Occurrence\OneOffOccurrenceIndexRepairer;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurringEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Tests\Support\FakeEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Prevents recurrence data from ever falling through to one-off projection.
 */
#[CoversClass( OccurrenceIndexRepairer::class )]
final class OccurrenceIndexRepairerTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/** Reset canonical metadata. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** A valid aggregate always uses recurring projection and canonical status. */
	public function test_repairs_recurring_aggregate_with_production_window(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$projector        = new FakeRecurringEventOccurrenceProjector();
		WordPressState::update_post_meta( 42, EventMeta::STATUS, EventStatus::POSTPONED->value );

		$result = ( new OccurrenceIndexRepairer(
			$store,
			$projector,
			windows: new OccurrenceRepairWindowFactory()
		) )->repair( 42, 'publish' );

		self::assertSame( OccurrenceIndexRepairStatus::INDEXED, $result );
		self::assertSame( 1, $projector->calls );
		self::assertSame( EventStatus::POSTPONED, $projector->status );
		self::assertNotNull( $projector->window );
		self::assertSame( 1_000, $projector->window->max_rows() );
	}

	/** Corrupt non-empty recurrence stays dirty and never reaches a projector. */
	public function test_corrupt_recurrence_is_invalid_and_fail_closed(): void {
		$store          = new FakeRecurrenceAggregateStore();
		$store->corrupt = true;
		$projector      = new FakeRecurringEventOccurrenceProjector();

		$result = ( new OccurrenceIndexRepairer( $store, $projector ) )->repair( 42, 'publish' );

		self::assertSame( OccurrenceIndexRepairStatus::INVALID, $result );
		self::assertSame( 0, $projector->calls );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/** Absence of recurrence delegates canonical data to the one-off repairer. */
	public function test_absent_recurrence_uses_one_off_repair(): void {
		$store     = new FakeRecurrenceAggregateStore();
		$recurring = new FakeRecurringEventOccurrenceProjector();
		$one_off   = new FakeEventOccurrenceProjector();
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, '2027-01-04' );
		WordPressState::update_post_meta( 42, EventMeta::END_LOCAL, '2027-01-04' );
		WordPressState::update_post_meta( 42, EventMeta::ALL_DAY, true );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );

		$result = ( new OccurrenceIndexRepairer(
			$store,
			$recurring,
			new OneOffOccurrenceIndexRepairer(
				new EventValidator(),
				new EventPublicationPolicy(),
				$one_off
			)
		) )->repair( 42, 'publish' );

		self::assertSame( OccurrenceIndexRepairStatus::INDEXED, $result );
		self::assertSame( 0, $recurring->calls );
		self::assertNotNull( $one_off->projection );
	}

	/** Return one minimal valid recurrence aggregate. */
	private function aggregate(): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04', null, true, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, '2027-01-04', $range, RecurrenceRule::daily() ) )
		);
	}
}
