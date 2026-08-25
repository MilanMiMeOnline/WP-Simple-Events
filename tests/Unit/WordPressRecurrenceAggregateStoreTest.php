<?php
/**
 * Tests for WordPress recurrence aggregate persistence.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateWriteStatus;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves atomic string replacement, missing state and corrupt-state distinction.
 */
#[CoversClass( WordPressRecurrenceAggregateStore::class )]
final class WordPressRecurrenceAggregateStoreTest extends TestCase {
	/**
	 * Reset deterministic WordPress metadata before each test.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * One aggregate persists and loads through its canonical JSON representation.
	 */
	public function test_complete_aggregate_is_replaced_and_loaded(): void {
		$aggregate = $this->aggregate();
		$store     = new WordPressRecurrenceAggregateStore();

		self::assertNull( $store->load( 42 ) );
		self::assertTrue( $store->replace( 42, $aggregate ) );
		self::assertEquals( $aggregate, $store->load( 42 ) );
		self::assertIsString( WordPressState::post_meta( 42, EventMeta::RECURRENCE ) );
	}

	/**
	 * A failed metadata write leaves the previous canonical value untouched.
	 */
	public function test_failed_replace_preserves_previous_value(): void {
		WordPressState::update_post_meta( 42, EventMeta::RECURRENCE, '{"previous":true}' );
		WordPressState::fail_meta_operations( true );

		self::assertFalse( ( new WordPressRecurrenceAggregateStore() )->replace( 42, $this->aggregate() ) );
		self::assertSame( '{"previous":true}', WordPressState::post_meta( 42, EventMeta::RECURRENCE ) );
	}

	/**
	 * Corrupt non-empty state never masquerades as an intentionally one-off event.
	 */
	public function test_corrupt_storage_fails_closed(): void {
		WordPressState::update_post_meta( 42, EventMeta::RECURRENCE, '{broken' );
		$this->expectException( InvalidArgumentException::class );

		( new WordPressRecurrenceAggregateStore() )->load( 42 );
	}

	/**
	 * A current revision performs one conditional canonical replacement.
	 */
	public function test_compare_and_replace_accepts_current_revision(): void {
		$store    = new WordPressRecurrenceAggregateStore();
		$snapshot = $store->snapshot( 42 );

		self::assertSame( ( new RecurrenceAggregateRevision() )->token( null ), $snapshot->revision );
		self::assertSame(
			RecurrenceAggregateWriteStatus::STORED,
			$store->replace_if_current( 42, $this->aggregate(), $snapshot->revision )
		);
		self::assertEquals( $this->aggregate(), $store->load( 42 ) );
	}

	/**
	 * A stale editor cannot overwrite a newer aggregate.
	 */
	public function test_compare_and_replace_rejects_stale_revision(): void {
		$store = new WordPressRecurrenceAggregateStore();
		self::assertTrue( $store->replace( 42, $this->aggregate() ) );
		$stale = $store->snapshot( 42 );
		self::assertTrue( $store->replace( 42, $this->aggregate( 2 ) ) );

		self::assertSame(
			RecurrenceAggregateWriteStatus::CONFLICT,
			$store->replace_if_current( 42, $this->aggregate( 3 ), $stale->revision )
		);
		self::assertEquals( $this->aggregate( 2 ), $store->load( 42 ) );
	}

	/**
	 * A current revision removes exactly the previewed aggregate.
	 */
	public function test_compare_and_delete_accepts_current_revision(): void {
		$store = new WordPressRecurrenceAggregateStore();
		self::assertTrue( $store->replace( 42, $this->aggregate() ) );
		$snapshot = $store->snapshot( 42 );

		self::assertSame(
			RecurrenceAggregateWriteStatus::STORED,
			$store->remove_if_current( 42, $snapshot->revision )
		);
		self::assertNull( $store->load( 42 ) );
	}

	/**
	 * A stale editor cannot remove a newer aggregate.
	 */
	public function test_compare_and_delete_rejects_stale_revision(): void {
		$store = new WordPressRecurrenceAggregateStore();
		self::assertTrue( $store->replace( 42, $this->aggregate() ) );
		$stale = $store->snapshot( 42 );
		self::assertTrue( $store->replace( 42, $this->aggregate( 2 ) ) );

		self::assertSame(
			RecurrenceAggregateWriteStatus::CONFLICT,
			$store->remove_if_current( 42, $stale->revision )
		);
		self::assertEquals( $this->aggregate( 2 ), $store->load( 42 ) );
	}

	/**
	 * An already one-off event is an unchanged delete.
	 */
	public function test_compare_and_delete_is_unchanged_without_recurrence(): void {
		$store    = new WordPressRecurrenceAggregateStore();
		$snapshot = $store->snapshot( 42 );

		self::assertSame(
			RecurrenceAggregateWriteStatus::UNCHANGED,
			$store->remove_if_current( 42, $snapshot->revision )
		);
	}

	/**
	 * Return one minimal recurring aggregate.
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
