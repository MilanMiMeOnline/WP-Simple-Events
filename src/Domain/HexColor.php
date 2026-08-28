<?php
/**
 * Strict hexadecimal color value helpers.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/** Normalizes one bounded color and derives accessible monochrome text. */
final class HexColor {
	/**
	 * Normalize one strict six-digit hexadecimal color.
	 *
	 * @param mixed $value Untrusted color value.
	 */
	public static function normalize( mixed $value ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^#[0-9a-f]{6}$/Di', $value ) ) {
			return '';
		}

		return strtolower( $value );
	}

	/**
	 * Select black or white by the greater WCAG contrast ratio.
	 *
	 * @param mixed $background Untrusted background value.
	 */
	public static function contrast_text( mixed $background ): string {
		$background = self::normalize( $background );

		if ( '' === $background ) {
			return '';
		}

		$luminance      = self::relative_luminance( $background );
		$black_contrast = ( $luminance + 0.05 ) / 0.05;
		$white_contrast = 1.05 / ( $luminance + 0.05 );

		return $black_contrast >= $white_contrast ? '#000000' : '#ffffff';
	}

	/**
	 * Return the WCAG relative luminance of one normalized background.
	 *
	 * @param string $background Normalized background color.
	 */
	private static function relative_luminance( string $background ): float {
		$channels = array(
			hexdec( substr( $background, 1, 2 ) ) / 255,
			hexdec( substr( $background, 3, 2 ) ) / 255,
			hexdec( substr( $background, 5, 2 ) ) / 255,
		);

		$linear = array_map(
			static fn ( float $channel ): float => $channel <= 0.04045
				? $channel / 12.92
				: ( ( $channel + 0.055 ) / 1.055 ) ** 2.4,
			$channels
		);

		return ( 0.2126 * $linear[0] ) + ( 0.7152 * $linear[1] ) + ( 0.0722 * $linear[2] );
	}
}
