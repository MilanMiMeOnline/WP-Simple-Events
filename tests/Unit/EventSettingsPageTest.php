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
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
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
		WordPressState::reset();
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

	/**
	 * Settings report the authoritative IANA zone and link to WordPress settings.
	 */
	public function test_renders_site_timezone_guidance_for_administrators(): void {
		WordPressState::allow_current_user( true );
		WordPressState::set_option( 'timezone_string', 'Europe/Brussels' );

		ob_start();
		( new EventSettingsPage() )->render_site_timezone_field();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( '<code>Europe/Brussels</code>', $output );
		self::assertStringContainsString( 'options-general.php', $output );
		self::assertStringContainsString( 'New events capture this timezone', $output );
		self::assertStringNotContainsString( 'does not adjust for daylight saving time', $output );
	}

	/**
	 * Fixed-offset guidance explicitly states that DST is not available.
	 */
	public function test_renders_fixed_offset_daylight_saving_warning(): void {
		WordPressState::allow_current_user( true );
		WordPressState::set_option( 'timezone_string', '' );
		WordPressState::set_option( 'gmt_offset', -4 );

		ob_start();
		( new EventSettingsPage() )->render_site_timezone_field();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( '<code>-04:00</code>', $output );
		self::assertStringContainsString( 'does not adjust for daylight saving time', $output );
	}

	/**
	 * The WordPress General Settings link is never exposed without admin rights.
	 */
	public function test_omits_general_settings_link_without_manage_options(): void {
		WordPressState::allow_current_user( false );
		WordPressState::set_option( 'timezone_string', 'Europe/Brussels' );

		ob_start();
		( new EventSettingsPage() )->render_site_timezone_field();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( '<code>Europe/Brussels</code>', $output );
		self::assertStringNotContainsString( 'options-general.php', $output );
	}
}
