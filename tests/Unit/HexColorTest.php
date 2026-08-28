<?php
/**
 * Tests for strict hexadecimal color handling.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\HexColor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( HexColor::class )]
/** Proves that stored and rendered colors stay bounded and accessible. */
final class HexColorTest extends TestCase {
	/** Six-digit values normalize while shorthand, alpha and CSS are rejected. */
	public function test_normalizes_only_strict_six_digit_hexadecimal_values(): void {
		self::assertSame( '#aabbcc', HexColor::normalize( '#AABBCC' ) );
		self::assertSame( '', HexColor::normalize( '#abc' ) );
		self::assertSame( '', HexColor::normalize( '#11223344' ) );
		self::assertSame( '', HexColor::normalize( 'red;display:none' ) );
		self::assertSame( '', HexColor::normalize( array( '#112233' ) ) );
	}

	/** Foreground selection uses the greater black/white WCAG contrast ratio. */
	public function test_derives_the_higher_contrast_black_or_white_foreground(): void {
		self::assertSame( '#ffffff', HexColor::contrast_text( '#000000' ) );
		self::assertSame( '#000000', HexColor::contrast_text( '#ffffff' ) );
		self::assertSame( '#000000', HexColor::contrast_text( '#92c94b' ) );
		self::assertSame( '', HexColor::contrast_text( 'transparent' ) );
	}
}
