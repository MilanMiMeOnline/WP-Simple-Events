<?php
/**
 * Tests for protected event maintenance endpoints.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventMaintenanceController;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventMaintenanceController::class )]
/**
 * Verifies that maintenance is exposed only through authenticated admin-post hooks.
 */
final class EventMaintenanceControllerTest extends TestCase {
	/**
	 * Reset registered hooks.
	 */
	protected function setUp(): void {
		HookRecorder::reset();
	}

	/**
	 * Both state-changing handlers require the authenticated admin-post boundary.
	 */
	public function test_registers_authenticated_maintenance_hooks(): void {
		$controller = new EventMaintenanceController();

		$controller->register();

		self::assertSame(
			array( $controller, 'repair_capabilities' ),
			HookRecorder::action( 'admin_post_' . EventMaintenanceController::REPAIR_CAPABILITIES_ACTION )
		);
		self::assertSame(
			array( $controller, 'reindex_events' ),
			HookRecorder::action( 'admin_post_' . EventMaintenanceController::REINDEX_ACTION )
		);
		self::assertNull( HookRecorder::action( 'admin_post_nopriv_' . EventMaintenanceController::REINDEX_ACTION ) );
	}
}
