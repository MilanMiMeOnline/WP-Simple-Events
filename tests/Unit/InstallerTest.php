<?php
/**
 * Tests for schema installation orchestration.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Access\EventCapabilities;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Routing\EventArchiveRewriteManager;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceTable;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Ensures schema state is committed only after the derived table exists.
 */
#[CoversClass( Installer::class )]
final class InstallerTest extends TestCase {
	/**
	 * Reset deterministic WordPress state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Successful table installation commits the version and grants roles.
	 */
	public function test_install_commits_schema_only_after_table_success(): void {
		$administrator = WordPressState::add_role( 'administrator' );
		$table         = new FakeOccurrenceTable();
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, true );

		$result = ( new Installer( $table ) )->install();

		self::assertTrue( $result );
		self::assertSame( 1, $table->install_calls );
		self::assertSame( Installer::SCHEMA_VERSION, WordPressState::option( Installer::VERSION_OPTION ) );
		self::assertContains( EventCapabilities::EDIT_POSTS, $administrator->capabilities() );
		self::assertFalse( WordPressState::has_option( OccurrenceIndexMigrationController::COMPLETE_OPTION ) );
		self::assertSame( 'events', WordPressState::option( EventArchiveRewriteManager::PENDING_OPTION ) );
	}

	/**
	 * Failure leaves both schema state and roles untouched so the upgrade retries.
	 */
	public function test_install_failure_does_not_claim_schema_or_grant_roles(): void {
		$administrator = WordPressState::add_role( 'administrator' );
		$table         = new FakeOccurrenceTable( install_result: false );

		$result = ( new Installer( $table ) )->install();

		self::assertFalse( $result );
		self::assertFalse( WordPressState::has_option( Installer::VERSION_OPTION ) );
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );
		self::assertSame( array(), $administrator->capabilities() );
	}

	/**
	 * Re-activation of the same schema does not restart a completed migration.
	 */
	public function test_same_schema_reinstall_preserves_completed_migration(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, true );

		$result = ( new Installer( new FakeOccurrenceTable() ) )->install();

		self::assertTrue( $result );
		self::assertTrue( WordPressState::option( OccurrenceIndexMigrationController::COMPLETE_OPTION ) );
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );
	}

	/** A schema upgrade schedules the currently configured route for one late flush. */
	public function test_schema_upgrade_schedules_the_custom_archive_route(): void {
		WordPressState::set_option( EventArchiveSettings::SLUG_OPTION, 'agenda' );

		$result = ( new Installer( new FakeOccurrenceTable() ) )->install();

		self::assertTrue( $result );
		self::assertSame( 'agenda', WordPressState::option( EventArchiveRewriteManager::PENDING_OPTION ) );
	}
}
