<?php
/**
 * Add-to-calendar Gutenberg block setting normalization.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarStyle;

/** Keeps block-authored style values inside a small safe CSS contract. */
final class AddToCalendarBlockSettings {
	/**
	 * Return one strict hexadecimal color or an empty theme-inheriting value.
	 *
	 * @param mixed $value Parsed block attribute.
	 */
	public static function color( mixed $value ): string {
		return AddToCalendarStyle::color( $value );
	}

	/**
	 * Return one optional bounded integer without coercing other shapes.
	 *
	 * @param mixed $value   Parsed block attribute.
	 * @param int   $minimum Inclusive lower bound.
	 * @param int   $maximum Inclusive upper bound.
	 */
	public static function integer( mixed $value, int $minimum, int $maximum ): ?int {
		return AddToCalendarStyle::integer( $value, $minimum, $maximum );
	}

	/**
	 * Build a bounded inline custom-property declaration.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 */
	public static function style( array $attributes ): string {
		return AddToCalendarStyle::inline_style( $attributes );
	}
}
