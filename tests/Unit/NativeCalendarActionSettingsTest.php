<?php
/**
 * Tests for the native Add to Calendar display preference.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Frontend\NativeCalendarActionSettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( NativeCalendarActionSettings::class )]
/** Verifies the strict off-by-default native action contract. */
final class NativeCalendarActionSettingsTest extends TestCase {
	/** Reset deterministic option state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** Only explicit checkbox values enable native output. */
	public function test_is_disabled_by_default_and_sanitizes_strictly(): void {
		$settings = new NativeCalendarActionSettings();

		self::assertFalse( $settings->enabled() );
		self::assertTrue( $settings->sanitize( true ) );
		self::assertTrue( $settings->sanitize( 1 ) );
		self::assertTrue( $settings->sanitize( '1' ) );
		self::assertFalse( $settings->sanitize( 'yes' ) );
		self::assertFalse( $settings->sanitize( array( '1' ) ) );

		WordPressState::set_option( NativeCalendarActionSettings::OPTION, '1' );
		self::assertTrue( $settings->enabled() );
	}
}
