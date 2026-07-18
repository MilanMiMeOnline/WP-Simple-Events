<?php
/**
 * Tests for safe derived event date-index repair.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Maintenance\EventDateIndexRepairer;
use MiMe\WPSimpleEvents\Maintenance\EventDateIndexRepairStatus;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventDateIndexRepairer::class )]
#[CoversClass( EventDateIndexRepairStatus::class )]
/**
 * Verifies strict input handling and UTC-only mutation.
 */
final class EventDateIndexRepairerTest extends TestCase {
	/**
	 * Reset deterministic metadata.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * A valid timed range replaces stale indexes without touching canonical data.
	 */
	public function test_repairs_stale_timed_indexes(): void {
		$this->store_range( 41, '2026-07-20T09:30:00', '2026-07-20T11:00:00', false, 'Europe/Brussels' );
		WordPressState::update_post_meta( 41, EventMeta::START_UTC, 10 );
		WordPressState::update_post_meta( 41, EventMeta::END_UTC, 20 );
		WordPressState::update_post_meta( 41, EventMeta::DATES_NEED_REVIEW, true );

		$result = ( new EventDateIndexRepairer() )->repair( 41, 'publish' );

		self::assertSame( EventDateIndexRepairStatus::REPAIRED, $result );
		self::assertSame( 1_784_532_600, WordPressState::post_meta( 41, EventMeta::START_UTC ) );
		self::assertSame( 1_784_538_000, WordPressState::post_meta( 41, EventMeta::END_UTC ) );
		self::assertSame( '2026-07-20T09:30:00', WordPressState::post_meta( 41, EventMeta::START_LOCAL ) );
		self::assertTrue( WordPressState::post_meta( 41, EventMeta::DATES_NEED_REVIEW ) );
	}

	/**
	 * All-day indexes use the captured timezone across a DST transition day.
	 */
	public function test_repairs_all_day_indexes_across_dst_boundary(): void {
		$this->store_range( 47, '2026-03-29', '2026-03-29', true, 'Europe/Brussels' );

		$result = ( new EventDateIndexRepairer() )->repair( 47, 'publish' );

		self::assertSame( EventDateIndexRepairStatus::REPAIRED, $result );
		self::assertSame( 1_774_738_800, WordPressState::post_meta( 47, EventMeta::START_UTC ) );
		self::assertSame( 1_774_821_599, WordPressState::post_meta( 47, EventMeta::END_UTC ) );
	}

	/**
	 * Numeric-string indexes equivalent to the derived timestamps need no write.
	 */
	public function test_recognizes_already_correct_indexes(): void {
		$this->store_range( 42, '2026-07-20T09:30:00', '2026-07-20T11:00:00', false, 'Europe/Brussels' );
		WordPressState::update_post_meta( 42, EventMeta::START_UTC, '1784532600' );
		WordPressState::update_post_meta( 42, EventMeta::END_UTC, '1784538000' );

		$result = ( new EventDateIndexRepairer() )->repair( 42, 'publish' );

		self::assertSame( EventDateIndexRepairStatus::UNCHANGED, $result );
	}

	/**
	 * Invalid canonical data is reported and its existing indexes remain intact.
	 */
	public function test_skips_invalid_canonical_range_without_mutation(): void {
		$this->store_range( 43, '2026-07-20T11:00:00', '2026-07-20T09:30:00', false, 'Europe/Brussels' );
		WordPressState::update_post_meta( 43, EventMeta::START_UTC, 111 );
		WordPressState::update_post_meta( 43, EventMeta::END_UTC, 222 );

		$result = ( new EventDateIndexRepairer() )->repair( 43, 'publish' );

		self::assertSame( EventDateIndexRepairStatus::INVALID, $result );
		self::assertSame( 111, WordPressState::post_meta( 43, EventMeta::START_UTC ) );
		self::assertSame( 222, WordPressState::post_meta( 43, EventMeta::END_UTC ) );
	}

	/**
	 * An incomplete draft safely loses stale derived indexes without needing a timezone.
	 */
	public function test_clears_stale_indexes_from_incomplete_draft(): void {
		WordPressState::update_post_meta( 44, EventMeta::START_UTC, 111 );
		WordPressState::update_post_meta( 44, EventMeta::END_UTC, 222 );

		$result = ( new EventDateIndexRepairer() )->repair( 44, 'draft' );

		self::assertSame( EventDateIndexRepairStatus::CLEARED, $result );
		self::assertFalse( WordPressState::has_post_meta( 44, EventMeta::START_UTC ) );
		self::assertFalse( WordPressState::has_post_meta( 44, EventMeta::END_UTC ) );
	}

	/**
	 * A published event without canonical dates remains untouched for manual review.
	 */
	public function test_skips_incomplete_published_event(): void {
		WordPressState::update_post_meta( 45, EventMeta::START_UTC, 111 );

		$result = ( new EventDateIndexRepairer() )->repair( 45, 'publish' );

		self::assertSame( EventDateIndexRepairStatus::INVALID, $result );
		self::assertSame( 111, WordPressState::post_meta( 45, EventMeta::START_UTC ) );
	}

	/**
	 * Persistence failures are distinguishable and can be retried safely.
	 */
	public function test_reports_metadata_write_failure(): void {
		$this->store_range( 46, '2026-07-20T09:30:00', '2026-07-20T11:00:00', false, 'Europe/Brussels' );
		WordPressState::fail_meta_operations( true );

		$result = ( new EventDateIndexRepairer() )->repair( 46, 'publish' );

		self::assertSame( EventDateIndexRepairStatus::FAILED, $result );
		self::assertFalse( WordPressState::has_post_meta( 46, EventMeta::START_UTC ) );
	}

	/**
	 * Store one canonical event date range.
	 *
	 * @param int    $post_id     Event ID.
	 * @param string $start_local Canonical local start.
	 * @param string $end_local   Canonical local end.
	 * @param bool   $all_day     All-day flag.
	 * @param string $timezone    Captured timezone.
	 */
	private function store_range(
		int $post_id,
		string $start_local,
		string $end_local,
		bool $all_day,
		string $timezone
	): void {
		WordPressState::update_post_meta( $post_id, EventMeta::START_LOCAL, $start_local );
		WordPressState::update_post_meta( $post_id, EventMeta::END_LOCAL, $end_local );
		WordPressState::update_post_meta( $post_id, EventMeta::ALL_DAY, $all_day );
		WordPressState::update_post_meta( $post_id, EventMeta::TIMEZONE, $timezone );
	}
}
