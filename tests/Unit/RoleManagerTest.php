<?php
/**
 * Tests for event role capability installation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Access\EventCapabilities;
use MiMe\WPSimpleEvents\Access\RoleManager;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies role grants and WooCommerce role isolation.
 */
#[CoversClass( RoleManager::class )]
final class RoleManagerTest extends TestCase {
	/**
	 * Reset test WordPress state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Administrator and editor receive the complete primitive set only.
	 */
	public function test_grants_editorial_capabilities_to_selected_roles(): void {
		$administrator = WordPressState::add_role( 'administrator' );
		$editor        = WordPressState::add_role( 'editor' );
		$shop_manager  = WordPressState::add_role( 'shop_manager' );

		( new RoleManager() )->grant();

		self::assertSame( EventCapabilities::editorial(), $administrator->capabilities() );
		self::assertSame( EventCapabilities::editorial(), $editor->capabilities() );
		self::assertSame( array(), $shop_manager->capabilities() );
	}

	/**
	 * Missing optional roles do not make installation fail.
	 */
	public function test_missing_role_is_ignored(): void {
		$administrator = WordPressState::add_role( 'administrator' );

		( new RoleManager() )->grant();

		self::assertSame( EventCapabilities::editorial(), $administrator->capabilities() );
	}

	/**
	 * Uninstall removes only capabilities the plugin granted to its selected roles.
	 */
	public function test_revokes_editorial_capabilities_from_selected_roles(): void {
		$administrator = WordPressState::add_role( 'administrator' );
		$editor        = WordPressState::add_role( 'editor' );

		$manager = new RoleManager();
		$manager->grant();
		$manager->revoke();

		self::assertSame( array(), $administrator->capabilities() );
		self::assertSame( array(), $editor->capabilities() );
	}
}
