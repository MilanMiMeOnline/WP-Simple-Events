<?php
/**
 * Tests for occurrence index health states.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceHealthMonitor;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceHealthStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceTable;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves the administrator state machine fails closed in the right order.
 */
#[CoversClass( OccurrenceHealthMonitor::class )]
final class OccurrenceHealthMonitorTest extends TestCase {
	/** Reset options. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** A missing schema never performs a coverage query. */
	public function test_schema_mismatch_is_unavailable(): void {
		$coverage = new FakeOccurrenceCoverageProbe( true );
		$status   = ( new OccurrenceHealthMonitor( new FakeOccurrenceTable(), $coverage ) )->status();

		self::assertSame( OccurrenceHealthStatus::UNAVAILABLE, $status );
		self::assertSame( 0, $coverage->calls );
	}

	/** An installed schema remains building until migration completion is explicit. */
	public function test_incomplete_migration_is_building(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );

		self::assertSame(
			OccurrenceHealthStatus::BUILDING,
			( new OccurrenceHealthMonitor( new FakeOccurrenceTable(), new FakeOccurrenceCoverageProbe() ) )->status()
		);
	}

	/** Complete migration with a public gap requires repair. */
	public function test_public_gap_requires_repair(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, true );

		self::assertSame(
			OccurrenceHealthStatus::REPAIR_NEEDED,
			( new OccurrenceHealthMonitor( new FakeOccurrenceTable(), new FakeOccurrenceCoverageProbe( true ) ) )->status()
		);
	}

	/** Complete schema and coverage is healthy. */
	public function test_complete_coverage_is_healthy(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, '1' );

		self::assertSame(
			OccurrenceHealthStatus::HEALTHY,
			( new OccurrenceHealthMonitor( new FakeOccurrenceTable(), new FakeOccurrenceCoverageProbe() ) )->status()
		);
	}
}
