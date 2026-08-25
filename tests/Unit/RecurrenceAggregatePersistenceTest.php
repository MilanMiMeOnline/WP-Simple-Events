<?php
/**
 * Tests for authorized recurrence aggregate replacement.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\RecurrenceAggregatePersistence;
use MiMe\WPSimpleEvents\Application\RecurrencePersistenceError;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves event authorization, identity binding and dirty-before-write ordering.
 */
#[CoversClass( RecurrenceAggregatePersistence::class )]
final class RecurrenceAggregatePersistenceTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/**
	 * Configure one canonical event and identity before each test.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);
		WordPressState::update_post_meta( 42, EventMeta::SERIES_UID, self::SERIES_UID );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );
	}

	/**
	 * An authorized identity-matched write marks derived state dirty before storage.
	 */
	public function test_authorized_replacement_is_stored_and_marked_dirty(): void {
		WordPressState::allow_current_user( true );
		$store  = new FakeRecurrenceAggregateStore();
		$result = ( new RecurrenceAggregatePersistence( $store ) )->replace( 42, $this->aggregate() );

		self::assertTrue( $result->successful() );
		self::assertTrue( $result->changed() );
		self::assertNull( $result->error() );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
		self::assertNotNull( $store->aggregate );
	}

	/**
	 * An unchanged canonical value neither rewrites storage nor dirties projection.
	 */
	public function test_unchanged_aggregate_is_a_clean_success(): void {
		WordPressState::allow_current_user( true );
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();

		$result = ( new RecurrenceAggregatePersistence( $store ) )->replace( 42, $this->aggregate() );

		self::assertTrue( $result->successful() );
		self::assertFalse( $result->changed() );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * Permission, identity and timezone failures never touch canonical storage.
	 */
	public function test_security_and_identity_failures_are_specific(): void {
		$store   = new FakeRecurrenceAggregateStore();
		$service = new RecurrenceAggregatePersistence( $store );

		self::assertSame( RecurrencePersistenceError::INVALID_EVENT, $service->replace( 999, $this->aggregate() )->error() );
		self::assertSame( RecurrencePersistenceError::FORBIDDEN, $service->replace( 42, $this->aggregate() )->error() );

		WordPressState::allow_current_user( true );
		WordPressState::update_post_meta( 42, EventMeta::SERIES_UID, '019c1d83-1798-4fac-a66d-ae8d67c46399' );
		self::assertSame( RecurrencePersistenceError::IDENTITY_MISMATCH, $service->replace( 42, $this->aggregate() )->error() );

		WordPressState::update_post_meta( 42, EventMeta::SERIES_UID, self::SERIES_UID );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'UTC' );
		self::assertSame( RecurrencePersistenceError::TIMEZONE_MISMATCH, $service->replace( 42, $this->aggregate() )->error() );
		self::assertNull( $store->aggregate );
	}

	/**
	 * Failure to establish the dirty guard blocks canonical replacement.
	 */
	public function test_dirty_guard_failure_blocks_storage(): void {
		WordPressState::allow_current_user( true );
		WordPressState::fail_meta_operations( true );
		$store  = new FakeRecurrenceAggregateStore();
		$result = ( new RecurrenceAggregatePersistence( $store ) )->replace( 42, $this->aggregate() );

		self::assertSame( RecurrencePersistenceError::INDEX_GUARD_FAILED, $result->error() );
		self::assertNull( $store->aggregate );
	}

	/**
	 * Storage failure leaves the dirty marker in place for explicit repair.
	 */
	public function test_storage_failure_remains_fail_closed(): void {
		WordPressState::allow_current_user( true );
		$result = ( new RecurrenceAggregatePersistence( new FakeRecurrenceAggregateStore( false ) ) )
			->replace( 42, $this->aggregate() );

		self::assertSame( RecurrencePersistenceError::STORAGE_FAILED, $result->error() );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * A current editor revision is stored through compare-and-replace.
	 */
	public function test_current_editor_revision_is_accepted(): void {
		WordPressState::allow_current_user( true );
		$store    = new FakeRecurrenceAggregateStore();
		$revision = $store->snapshot( 42 )->revision;
		$result   = ( new RecurrenceAggregatePersistence( $store ) )->replace( 42, $this->aggregate(), $revision );

		self::assertTrue( $result->successful() );
		self::assertTrue( $result->changed() );
		self::assertNotNull( $store->aggregate );
	}

	/**
	 * A stale or malformed revision cannot dirty or replace canonical state.
	 */
	public function test_stale_editor_revision_is_rejected_before_write(): void {
		WordPressState::allow_current_user( true );
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$stale            = ( new RecurrenceAggregateRevision() )->token( null );
		$result           = ( new RecurrenceAggregatePersistence( $store ) )->replace( 42, $this->aggregate( 2 ), $stale );

		self::assertSame( RecurrencePersistenceError::STALE_REVISION, $result->error() );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::INDEX_DIRTY ) );
		self::assertSame(
			RecurrencePersistenceError::STALE_REVISION,
			( new RecurrenceAggregatePersistence( $store ) )->replace( 42, $this->aggregate( 2 ), 'invalid' )->error()
		);
	}

	/**
	 * An intervening write at the atomic boundary fails closed after dirtying.
	 */
	public function test_compare_and_replace_race_is_reported(): void {
		WordPressState::allow_current_user( true );
		$store           = new FakeRecurrenceAggregateStore();
		$revision        = $store->snapshot( 42 )->revision;
		$store->conflict = true;
		$result          = ( new RecurrenceAggregatePersistence( $store ) )->replace( 42, $this->aggregate(), $revision );

		self::assertSame( RecurrencePersistenceError::STALE_REVISION, $result->error() );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
		self::assertNull( $store->aggregate );
	}

	/**
	 * Return one minimal recurring aggregate.
	 *
	 * @param int $interval Daily rule interval.
	 */
	private function aggregate( int $interval = 1 ): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04', null, true, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, '2027-01-04', $range, RecurrenceRule::daily( $interval ) ) )
		);
	}
}
