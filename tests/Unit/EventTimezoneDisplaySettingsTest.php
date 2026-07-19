<?php
/**
 * Tests for public event-timezone visibility settings.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Frontend\EventTimezoneDisplaySettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the backward-compatible default and strict boolean allowlist.
 */
#[CoversClass( EventTimezoneDisplaySettings::class )]
final class EventTimezoneDisplaySettingsTest extends TestCase {
	/** Reset deterministic options. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Public timezone labels remain disabled unless explicitly enabled.
	 */
	public function test_disabled_by_default_and_enabled_only_explicitly(): void {
		$settings = new EventTimezoneDisplaySettings();

		self::assertFalse( $settings->enabled() );

		foreach ( array( true, 1, '1' ) as $enabled ) {
			WordPressState::set_option( EventTimezoneDisplaySettings::OPTION, $enabled );
			self::assertTrue( $settings->enabled() );
		}

		foreach ( array( false, 0, '0', 'yes', array( '1' ) ) as $disabled ) {
			WordPressState::set_option( EventTimezoneDisplaySettings::OPTION, $disabled );
			self::assertFalse( $settings->enabled() );
		}
	}

	/**
	 * Settings API input uses the same strict boolean contract.
	 */
	public function test_sanitizer_uses_strict_enabled_allowlist(): void {
		$settings = new EventTimezoneDisplaySettings();

		self::assertTrue( $settings->sanitize( '1' ) );
		self::assertFalse( $settings->sanitize( 'true' ) );
		self::assertFalse( $settings->sanitize( array( '1' ) ) );
	}
}
