<?php
/**
 * Tests for occurrence migration scheduling.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Verifies idempotent scheduling and bounded continuation.
 */
#[CoversClass( OccurrenceIndexMigrationController::class )]
final class OccurrenceIndexMigrationControllerTest extends TestCase {
	/**
	 * Reset cron and option state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
	}

	/**
	 * Repeated ordinary requests queue at most one worker.
	 */
	public function test_schedule_is_idempotent(): void {
		$controller = new OccurrenceIndexMigrationController();

		$controller->schedule();
		$controller->schedule();

		self::assertSame( 1, WordPressState::scheduled_count( OccurrenceIndexMigrationController::HOOK ) );
	}

	/**
	 * A completed WordPress string option prevents another migration worker.
	 */
	public function test_completed_string_option_prevents_scheduling(): void {
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, '1' );

		( new OccurrenceIndexMigrationController() )->schedule();

		self::assertSame( 0, WordPressState::scheduled_count( OccurrenceIndexMigrationController::HOOK ) );
	}

	/**
	 * An empty batch completes migration without another worker.
	 */
	public function test_empty_worker_marks_migration_complete(): void {
		( new OccurrenceIndexMigrationController() )->run();

		self::assertTrue( WordPressState::option( OccurrenceIndexMigrationController::COMPLETE_OPTION ) );
		self::assertSame( 0, WordPressState::scheduled_count( OccurrenceIndexMigrationController::HOOK ) );
	}

	/**
	 * A full bounded batch schedules continuation instead of looping inline.
	 */
	public function test_full_batch_schedules_continuation(): void {
		foreach ( range( 1, 25 ) as $event_id ) {
			WordPressState::add_post(
				new WP_Post(
					array(
						'ID'          => $event_id,
						'post_type'   => EventPostType::POST_TYPE,
						'post_status' => 'publish',
					)
				)
			);
		}

		( new OccurrenceIndexMigrationController() )->run();

		self::assertSame( 1, WordPressState::scheduled_count( OccurrenceIndexMigrationController::HOOK ) );
		self::assertFalse( WordPressState::has_option( OccurrenceIndexMigrationController::COMPLETE_OPTION ) );
	}
}
