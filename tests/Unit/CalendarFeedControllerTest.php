<?php
/**
 * Tests for public calendar feed boundary validation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Rest\CalendarFeedController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies malformed requests are rejected before querying WordPress.
 */
#[CoversClass( CalendarFeedController::class )]
final class CalendarFeedControllerTest extends TestCase {
	/**
	 * ISO boundaries require a complete date-time and explicit timezone.
	 */
	public function test_iso_boundary_validation_is_strict(): void {
		$controller = new CalendarFeedController();

		self::assertTrue( $controller->valid_iso_boundary( '2026-07-01T00:00:00+02:00' ) );
		self::assertTrue( $controller->valid_iso_boundary( '2026-07-01T00:00:00Z' ) );
		self::assertFalse( $controller->valid_iso_boundary( '2026-07-01' ) );
		self::assertFalse( $controller->valid_iso_boundary( '2026-07-01T00:00:00' ) );
		self::assertFalse( $controller->valid_iso_boundary( array() ) );
	}

	/**
	 * Term lists have deterministic total, item and count bounds.
	 */
	public function test_slug_list_validation_is_bounded(): void {
		$controller = new CalendarFeedController();

		self::assertTrue( $controller->valid_slug_list( '' ) );
		self::assertTrue( $controller->valid_slug_list( 'workshops,summer-2026' ) );
		self::assertFalse( $controller->valid_slug_list( str_repeat( 'a', 201 ) ) );
		self::assertFalse( $controller->valid_slug_list( implode( ',', range( 1, 21 ) ) ) );
		self::assertFalse( $controller->valid_slug_list( 'workshops,***' ) );
		self::assertFalse( $controller->valid_slug_list( array( 'workshops' ) ) );
	}
}
