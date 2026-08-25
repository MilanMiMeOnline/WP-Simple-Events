<?php
/**
 * Tests for recurrence-to-one-off conversion coordination.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\RecurrenceDisableCoordinator;
use MiMe\WPSimpleEvents\Application\RecurrencePersistenceError;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceSource;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Tests\Support\FakeEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves explicit survivor conversion, rollback and fail-closed projection.
 */
#[CoversClass( RecurrenceDisableCoordinator::class )]
final class RecurrenceDisableCoordinatorTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/**
	 * Configure one editable recurring event with an older canonical date.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::allow_current_user( true );
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);

		WordPressState::update_post_meta( 42, EventMeta::SERIES_UID, self::SERIES_UID );
		$this->store_date( EventDateRange::from_local( '2027-01-04', null, true, 'Europe/Brussels' ) );
		WordPressState::update_post_meta( 42, EventMeta::STATUS, EventStatus::SCHEDULED->value );
	}

	/**
	 * The selected effective occurrence becomes the complete one-off event.
	 */
	public function test_selected_occurrence_is_retained_as_one_off(): void {
		$store             = $this->recurring_store();
		$projector         = new FakeEventOccurrenceProjector();
		$survivor          = $this->survivor();
		$previous_revision = $store->snapshot( 42 )->revision;

		$result = ( new RecurrenceDisableCoordinator( $store, $projector ) )->disable(
			42,
			$survivor,
			$previous_revision
		);

		self::assertTrue( $result->successful() );
		self::assertTrue( $result->changed() );
		self::assertNull( $store->aggregate );
		self::assertSame( '2027-01-06T18:30:00', WordPressState::post_meta( 42, EventMeta::START_LOCAL ) );
		self::assertSame( EventStatus::POSTPONED->value, WordPressState::post_meta( 42, EventMeta::STATUS ) );
		self::assertSame( $survivor->date_range, $projector->projection['date_range'] );
		self::assertSame( EventStatus::POSTPONED, $projector->projection['status'] );
	}

	/**
	 * A stale delete restores the original canonical dates and keeps the series.
	 */
	public function test_stale_delete_rolls_back_prepared_metadata(): void {
		$store             = $this->recurring_store();
		$expected_revision = $store->snapshot( 42 )->revision;
		$store->conflict   = true;

		$result = ( new RecurrenceDisableCoordinator( $store, new FakeEventOccurrenceProjector() ) )
			->disable( 42, $this->survivor(), $expected_revision );

		self::assertSame( RecurrencePersistenceError::STALE_REVISION, $result->error() );
		self::assertNotNull( $store->aggregate );
		self::assertSame( '2027-01-04', WordPressState::post_meta( 42, EventMeta::START_LOCAL ) );
		self::assertSame( EventStatus::SCHEDULED->value, WordPressState::post_meta( 42, EventMeta::STATUS ) );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * Projection failure keeps the authoritative one-off conversion repairable.
	 */
	public function test_projection_failure_keeps_one_off_canonical_state_dirty(): void {
		$store  = $this->recurring_store();
		$result = ( new RecurrenceDisableCoordinator( $store, new FakeEventOccurrenceProjector( false ) ) )
			->disable( 42, $this->survivor(), $store->snapshot( 42 )->revision );

		self::assertSame( RecurrencePersistenceError::PROJECTION_FAILED, $result->error() );
		self::assertTrue( $result->changed() );
		self::assertNull( $store->aggregate );
		self::assertSame( '2027-01-06T18:30:00', WordPressState::post_meta( 42, EventMeta::START_LOCAL ) );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * A failed dirty guard changes neither canonical representation.
	 */
	public function test_failed_dirty_guard_changes_nothing(): void {
		$store = $this->recurring_store();
		WordPressState::fail_meta_operations( true );

		$result = ( new RecurrenceDisableCoordinator( $store, new FakeEventOccurrenceProjector() ) )
			->disable( 42, $this->survivor(), $store->snapshot( 42 )->revision );

		self::assertSame( RecurrencePersistenceError::INDEX_GUARD_FAILED, $result->error() );
		self::assertNotNull( $store->aggregate );
		self::assertSame( '2027-01-04', WordPressState::post_meta( 42, EventMeta::START_LOCAL ) );
	}

	/**
	 * Create one fake containing a daily aggregate.
	 */
	private function recurring_store(): FakeRecurrenceAggregateStore {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();

		return $store;
	}

	/**
	 * Return one daily recurring aggregate.
	 */
	private function aggregate(): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04', null, true, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, '2027-01-04', $range, RecurrenceRule::daily() ) )
		);
	}

	/**
	 * Return one modified effective occurrence selected by the editor.
	 */
	private function survivor(): EventOccurrence {
		return new EventOccurrence(
			42,
			OccurrenceIdentity::from( self::SERIES_UID, '2027-01-06' ),
			1,
			0,
			OccurrenceSource::RULE,
			EventDateRange::from_local(
				'2027-01-06T18:30:00',
				'2027-01-06T20:00:00',
				false,
				'Europe/Brussels'
			),
			EventStatus::POSTPONED
		);
	}

	/**
	 * Persist a complete canonical date fixture.
	 *
	 * @param EventDateRange $range Date fixture.
	 */
	private function store_date( EventDateRange $range ): void {
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, $range->start_local() );
		WordPressState::update_post_meta( 42, EventMeta::END_LOCAL, $range->end_local() );
		WordPressState::update_post_meta( 42, EventMeta::START_UTC, $range->start_utc() );
		WordPressState::update_post_meta( 42, EventMeta::END_UTC, $range->end_utc() );
		WordPressState::update_post_meta( 42, EventMeta::ALL_DAY, $range->all_day() );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, $range->timezone() );
	}
}
