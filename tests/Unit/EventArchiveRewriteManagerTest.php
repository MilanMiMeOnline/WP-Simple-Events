<?php
/**
 * Tests for one-shot event archive rewrite maintenance.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Routing\EventArchiveRewriteManager;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventArchiveRewriteManager::class )]
/**
 * Verifies change-driven rewrite regeneration without request-time flushing.
 */
final class EventArchiveRewriteManagerTest extends TestCase {
	/**
	 * Reset hooks, options and rewrite counters.
	 */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/**
	 * Only the archive option can schedule a late one-shot flush.
	 */
	public function test_registers_only_specific_option_and_late_init_hooks(): void {
		$manager = new EventArchiveRewriteManager();

		$manager->register();

		self::assertSame( array( $manager, 'updated' ), HookRecorder::action( 'update_option_' . EventArchiveSettings::SLUG_OPTION ) );
		self::assertSame( array( $manager, 'added' ), HookRecorder::action( 'add_option_' . EventArchiveSettings::SLUG_OPTION ) );
		self::assertSame( array( $manager, 'maybe_flush' ), HookRecorder::action( 'init' ) );
	}

	/**
	 * Saving an equivalent slug does not create expensive rewrite work.
	 */
	public function test_schedules_only_a_real_normalized_slug_change(): void {
		$manager = new EventArchiveRewriteManager();

		$manager->updated( 'events', 'events' );
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );

		$manager->updated( 'events', 'community-events' );
		self::assertSame( 'community-events', WordPressState::option( EventArchiveRewriteManager::PENDING_OPTION ) );
	}

	/**
	 * The implicit default and a custom first save are distinguished.
	 */
	public function test_first_default_save_does_not_schedule_but_custom_save_does(): void {
		$manager = new EventArchiveRewriteManager();

		$manager->added( EventArchiveSettings::SLUG_OPTION, 'events' );
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );

		$manager->added( EventArchiveSettings::SLUG_OPTION, 'calendar' );
		self::assertSame( 'calendar', WordPressState::option( EventArchiveRewriteManager::PENDING_OPTION ) );
	}

	/**
	 * Matching pending state produces exactly one soft flush.
	 */
	public function test_flushes_once_only_when_pending_slug_matches_current_setting(): void {
		WordPressState::set_option( EventArchiveSettings::SLUG_OPTION, 'calendar' );
		WordPressState::set_option( EventArchiveRewriteManager::PENDING_OPTION, 'calendar' );
		$manager = new EventArchiveRewriteManager();

		$manager->maybe_flush();
		$manager->maybe_flush();

		self::assertSame( 1, WordPressState::rewrite_flushes() );
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );
	}

	/**
	 * Corrupt or obsolete internal state cannot cause a rewrite flush.
	 */
	public function test_discards_malformed_or_stale_pending_state_without_flushing(): void {
		WordPressState::set_option( EventArchiveSettings::SLUG_OPTION, 'events' );
		$manager = new EventArchiveRewriteManager();

		WordPressState::set_option( EventArchiveRewriteManager::PENDING_OPTION, false );
		$manager->maybe_flush();
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );

		WordPressState::set_option( EventArchiveRewriteManager::PENDING_OPTION, array( 'calendar' ) );
		$manager->maybe_flush();
		self::assertSame( 0, WordPressState::rewrite_flushes() );
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );

		WordPressState::set_option( EventArchiveRewriteManager::PENDING_OPTION, 'old-events' );
		$manager->maybe_flush();
		self::assertSame( 0, WordPressState::rewrite_flushes() );
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );
	}
}
