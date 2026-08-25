<?php
/**
 * Tests for recurring projection renewal scheduling.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionRenewalBatchProcessor;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionRenewalController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceIndexRepairer;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/** Proves schema gates, bounded continuation and offset cleanup. */
#[CoversClass( OccurrenceProjectionRenewalController::class )]
final class OccurrenceProjectionRenewalControllerTest extends TestCase {
	/** Reset hooks, options and scheduled jobs. */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/** Registration exposes one scheduler and one private worker. */
	public function test_registers_scheduler_and_worker(): void {
		( new OccurrenceProjectionRenewalController() )->register();

		self::assertIsCallable( HookRecorder::action( 'init' ) );
		self::assertIsCallable( HookRecorder::action( OccurrenceProjectionRenewalController::HOOK ) );
	}

	/** Scheduling requires both current schema and completed initial migration. */
	public function test_schedule_requires_ready_projection_schema(): void {
		$controller = new OccurrenceProjectionRenewalController();
		$controller->schedule();
		self::assertSame( 0, WordPressState::scheduled_count( OccurrenceProjectionRenewalController::HOOK ) );

		$this->ready();
		$controller->schedule();
		$controller->schedule();
		self::assertSame( 1, WordPressState::scheduled_count( OccurrenceProjectionRenewalController::HOOK ) );
	}

	/** Failed rows advance a bounded offset and a later short pass resets it. */
	public function test_worker_advances_and_resets_unresolved_offset(): void {
		$this->ready();

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

		$repairer   = new FakeOccurrenceIndexRepairer(
			array(
				1 => OccurrenceIndexRepairStatus::INVALID,
				2 => OccurrenceIndexRepairStatus::FAILED,
			)
		);
		$controller = new OccurrenceProjectionRenewalController(
			new OccurrenceProjectionRenewalBatchProcessor( $repairer )
		);

		$controller->run();
		self::assertSame( 2, WordPressState::option( OccurrenceProjectionRenewalController::OFFSET_OPTION ) );
		self::assertSame( 1, WordPressState::scheduled_count( OccurrenceProjectionRenewalController::HOOK ) );

		WordPressState::clear_scheduled( OccurrenceProjectionRenewalController::HOOK );
		$controller->run();
		self::assertFalse( WordPressState::has_option( OccurrenceProjectionRenewalController::OFFSET_OPTION ) );
		self::assertSame( 1, WordPressState::scheduled_count( OccurrenceProjectionRenewalController::HOOK ) );
	}

	/** Prepare current schema and migration state. */
	private function ready(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, true );
	}
}
