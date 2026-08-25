<?php
/**
 * Tests for scheduled inactive-generation cleanup.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceGenerationCleanupController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceGenerationCleaner;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves schema gating, one-job scheduling and bounded continuation policy.
 */
#[CoversClass( OccurrenceGenerationCleanupController::class )]
final class OccurrenceGenerationCleanupControllerTest extends TestCase {
	/** Reset deterministic hooks, options and scheduled events. */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/** Registration exposes one init scheduler and one private cron worker. */
	public function test_registers_scheduler_and_worker(): void {
		( new OccurrenceGenerationCleanupController( new FakeOccurrenceGenerationCleaner() ) )->register();

		self::assertIsCallable( HookRecorder::action( 'init' ) );
		self::assertIsCallable( HookRecorder::action( OccurrenceGenerationCleanupController::HOOK ) );
	}

	/** No maintenance work is scheduled before the matching schema exists. */
	public function test_schedule_requires_current_schema(): void {
		( new OccurrenceGenerationCleanupController( new FakeOccurrenceGenerationCleaner() ) )->schedule();

		self::assertSame( 0, WordPressState::scheduled_count( OccurrenceGenerationCleanupController::HOOK ) );
	}

	/** A current schema receives at most one initial maintenance job. */
	public function test_schedule_is_idempotent(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		$controller = new OccurrenceGenerationCleanupController( new FakeOccurrenceGenerationCleaner() );

		$controller->schedule();
		$controller->schedule();

		self::assertSame( 1, WordPressState::scheduled_count( OccurrenceGenerationCleanupController::HOOK ) );
	}

	/** One worker call always uses the fixed batch and 24-hour age boundary. */
	public function test_worker_uses_bounded_old_row_contract(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		$cleaner    = new FakeOccurrenceGenerationCleaner( 2 );
		$controller = new OccurrenceGenerationCleanupController( $cleaner );
		$before     = time() - 86400;

		$controller->run();

		self::assertCount( 1, $cleaner->calls );
		self::assertSame( 100, $cleaner->calls[0]['limit'] );
		self::assertGreaterThanOrEqual( $before, $cleaner->calls[0]['cutoff_utc'] );
		self::assertLessThanOrEqual( time() - 86400, $cleaner->calls[0]['cutoff_utc'] );
		self::assertSame( 1, WordPressState::scheduled_count( OccurrenceGenerationCleanupController::HOOK ) );
	}

	/** Full batches and failures both request one prompt bounded continuation. */
	public function test_full_batch_or_failure_schedules_one_continuation(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );

		foreach ( array( 100, null ) as $result ) {
			WordPressState::clear_scheduled( OccurrenceGenerationCleanupController::HOOK );
			( new OccurrenceGenerationCleanupController( new FakeOccurrenceGenerationCleaner( $result ) ) )->run();
			self::assertSame( 1, WordPressState::scheduled_count( OccurrenceGenerationCleanupController::HOOK ) );
		}
	}
}
