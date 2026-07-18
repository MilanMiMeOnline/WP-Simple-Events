<?php
/**
 * Tests for the event settings page.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventSettingsPage;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventSettingsPage::class )]
/**
 * Verifies settings registration and strict checkbox normalization.
 */
final class EventSettingsPageTest extends TestCase {
	/**
	 * Reset registered hooks.
	 */
	protected function setUp(): void {
		HookRecorder::reset();
	}

	/**
	 * The settings page uses the native admin menu and Settings API lifecycle.
	 */
	public function test_registers_admin_hooks(): void {
		$page = new EventSettingsPage();

		$page->register();

		self::assertSame( array( $page, 'register_menu' ), HookRecorder::action( 'admin_menu' ) );
		self::assertSame( array( $page, 'register_settings' ), HookRecorder::action( 'admin_init' ) );
		self::assertSame( array( $page, 'render_archive_conflict_notice' ), HookRecorder::action( 'admin_notices' ) );
	}

	/**
	 * Only the checkbox's explicit enabled values are accepted as true.
	 */
	public function test_sanitizes_structured_data_toggle_with_an_allowlist(): void {
		$page = new EventSettingsPage();

		self::assertTrue( $page->sanitize_checkbox( true ) );
		self::assertTrue( $page->sanitize_checkbox( 1 ) );
		self::assertTrue( $page->sanitize_checkbox( '1' ) );
		self::assertFalse( $page->sanitize_checkbox( false ) );
		self::assertFalse( $page->sanitize_checkbox( 0 ) );
		self::assertFalse( $page->sanitize_checkbox( 'yes' ) );
		self::assertFalse( $page->sanitize_checkbox( array( '1' ) ) );
	}
}
