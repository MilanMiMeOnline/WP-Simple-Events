<?php
/**
 * Tests for the optional Elementor version boundary.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Elementor\ElementorCompatibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ElementorCompatibility::class )]
/**
 * Verifies that Elementor stays optional and explicitly version-gated.
 */
final class ElementorCompatibilityTest extends TestCase {
	/**
	 * Accept only the documented Elementor compatibility range.
	 *
	 * @param string|null $version   Detected host version.
	 * @param bool        $supported Expected decision.
	 */
	#[DataProvider( 'version_provider' )]
	public function test_version_support_is_explicit( ?string $version, bool $supported ): void {
		self::assertSame( $supported, ElementorCompatibility::supports( $version ) );
	}

	/**
	 * Supply missing, malformed, old and supported versions.
	 *
	 * @return array<string, array{0: string|null, 1: bool}>
	 */
	public static function version_provider(): array {
		return array(
			'not installed'        => array( null, false ),
			'empty constant'       => array( '', false ),
			'malformed constant'   => array( 'latest', false ),
			'one patch too old'    => array( '3.34.9', false ),
			'minimum supported'    => array( '3.35.0', true ),
			'recent 3.x'           => array( '3.35.7', true ),
			'current tested 4.x'   => array( '4.1.5', true ),
			'future major version' => array( '5.0.0', true ),
		);
	}
}
