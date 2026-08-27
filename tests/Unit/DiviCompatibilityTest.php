<?php
/**
 * Tests for the optional Divi 5 version boundary.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Divi\DiviCompatibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( DiviCompatibility::class )]
/**
 * Verifies that Divi stays optional and explicitly version-gated.
 */
final class DiviCompatibilityTest extends TestCase {
	/**
	 * Accept only the qualified Divi 5 range.
	 *
	 * @param string|null $version   Detected host version.
	 * @param bool        $supported Expected compatibility decision.
	 */
	#[DataProvider( 'version_provider' )]
	public function test_version_support_is_explicit( ?string $version, bool $supported ): void {
		self::assertSame( $supported, DiviCompatibility::supports( $version ) );
	}

	/**
	 * Supply missing, malformed, old, current and future-major versions.
	 *
	 * @return array<string, array{0: string|null, 1: bool}>
	 */
	public static function version_provider(): array {
		return array(
			'not installed'          => array( null, false ),
			'empty constant'         => array( '', false ),
			'malformed constant'     => array( 'latest', false ),
			'divi 4'                 => array( '4.27.4', false ),
			'earlier unqualified d5' => array( '5.11.0', false ),
			'qualified floor'        => array( '5.11.1', true ),
			'later d5 patch'         => array( '5.11.2', true ),
			'later d5 minor'         => array( '5.12.0', true ),
			'future major'           => array( '6.0.0', false ),
		);
	}
}
