<?php
/**
 * Tests for the destructive uninstall preference.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Lifecycle\UninstallSettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( UninstallSettings::class )]
/**
 * Verifies that deletion requires an explicit allowlisted opt-in.
 */
final class UninstallSettingsTest extends TestCase {
	/**
	 * Reset deterministic options.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Missing and malformed options preserve all plugin data.
	 */
	public function test_defaults_to_data_retention(): void {
		$settings = new UninstallSettings();

		self::assertFalse( $settings->delete_data() );

		foreach ( array( false, 0, '0', 'yes', array( '1' ) ) as $value ) {
			WordPressState::set_option( UninstallSettings::OPTION, $value );
			self::assertFalse( $settings->delete_data() );
		}
	}

	/**
	 * Only explicit checkbox representations enable destructive cleanup.
	 */
	public function test_accepts_only_explicit_enabled_values(): void {
		$settings = new UninstallSettings();

		foreach ( array( true, 1, '1' ) as $value ) {
			WordPressState::set_option( UninstallSettings::OPTION, $value );
			self::assertTrue( $settings->delete_data() );
		}
	}
}
