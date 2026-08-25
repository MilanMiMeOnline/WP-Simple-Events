<?php
/**
 * Tests for occurrence index Site Health reporting.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\OccurrenceSiteHealthController;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceHealthMonitor;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceTable;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies Site Health receives the shared privacy-safe state.
 */
#[CoversClass( OccurrenceSiteHealthController::class )]
final class OccurrenceSiteHealthControllerTest extends TestCase {
	/** Reset hooks and options. */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/** The controller registers one direct test without replacing existing tests. */
	public function test_registers_and_preserves_existing_site_health_tests(): void {
		$controller = new OccurrenceSiteHealthController();
		$controller->register();

		self::assertSame( array( $controller, 'add_test' ), HookRecorder::action( 'site_status_tests' ) );
		$tests = $controller->add_test( array( 'direct' => array( 'core' => array( 'test' => 'core' ) ) ) );
		self::assertArrayHasKey( 'core', $tests['direct'] );
		self::assertSame( array( $controller, 'test' ), $tests['direct'][ OccurrenceSiteHealthController::TEST ]['test'] );
	}

	/** A public coverage gap is recommended and links administrators to repair. */
	public function test_reports_repair_state_without_event_details(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, true );
		WordPressState::allow_current_user( true );
		$controller = new OccurrenceSiteHealthController(
			new OccurrenceHealthMonitor( new FakeOccurrenceTable(), new FakeOccurrenceCoverageProbe( true ) )
		);

		$result = $controller->test();

		self::assertSame( 'recommended', $result['status'] );
		self::assertStringContainsString( 'needs repair', $result['label'] );
		self::assertStringContainsString( 'wpse-settings', $result['actions'] );
		self::assertStringNotContainsString( '_wpse_', $result['description'] );
	}
}
