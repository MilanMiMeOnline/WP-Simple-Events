<?php
/**
 * Tests for occurrence read-path readiness.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceTable;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves any schema, migration, table or per-event uncertainty retains fallback.
 */
#[CoversClass( OccurrenceReadiness::class )]
final class OccurrenceReadinessTest extends TestCase {
	/**
	 * Reset deterministic WordPress state and install-ready options.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, true );
	}

	/**
	 * Every gate must agree before occurrence reads may replace legacy metadata reads.
	 */
	public function test_ready_requires_schema_migration_table_and_complete_coverage(): void {
		$coverage = new FakeOccurrenceCoverageProbe();
		$gate     = new OccurrenceReadiness( new FakeOccurrenceTable(), $coverage );

		self::assertTrue( $gate->ready() );
		self::assertTrue( $gate->ready() );
		self::assertSame( 1, $coverage->calls, 'Readiness should be memoized for one request.' );
	}

	/**
	 * A known dirty or unindexed public event blocks the switch.
	 */
	public function test_public_coverage_gap_fails_closed(): void {
		$gate = new OccurrenceReadiness(
			new FakeOccurrenceTable(),
			new FakeOccurrenceCoverageProbe( has_gap: true )
		);

		self::assertFalse( $gate->ready() );
	}

	/**
	 * A missing physical table blocks the switch even if options claim completion.
	 */
	public function test_missing_table_fails_closed(): void {
		$coverage = new FakeOccurrenceCoverageProbe();
		$gate     = new OccurrenceReadiness(
			new FakeOccurrenceTable( exists_result: false ),
			$coverage
		);

		self::assertFalse( $gate->ready() );
		self::assertSame( 0, $coverage->calls );
	}

	/**
	 * WordPress' common persisted true representations are accepted, false is not.
	 */
	public function test_incomplete_migration_fails_closed(): void {
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, false );
		$coverage = new FakeOccurrenceCoverageProbe();
		$gate     = new OccurrenceReadiness( new FakeOccurrenceTable(), $coverage );

		self::assertFalse( $gate->ready() );
		self::assertSame( 0, $coverage->calls );
	}
}
