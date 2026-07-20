<?php
/**
 * Atomic Gutenberg block setting normalization.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

/** Validates parsed block attributes before they reach presentation services. */
final class EventFieldBlockSettings {
	/**
	 * Normalize a schema-shaped positive event identifier.
	 *
	 * @param mixed $value Parsed attribute value.
	 */
	public static function event_id( mixed $value ): ?int {
		return is_int( $value ) && $value > 0 ? $value : null;
	}

	/**
	 * Select one documented string value.
	 *
	 * @param mixed    $value    Parsed attribute value.
	 * @param string[] $allowed  Allowlisted values.
	 * @param string   $fallback Invalid-value fallback.
	 */
	public static function choice( mixed $value, array $allowed, string $fallback ): string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Normalize a strictly typed boolean attribute.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @param string               $key        Attribute name.
	 * @param bool                 $fallback   Missing or malformed fallback.
	 */
	public static function boolean( array $attributes, string $key, bool $fallback ): bool {
		return is_bool( $attributes[ $key ] ?? null ) ? $attributes[ $key ] : $fallback;
	}

	/**
	 * Normalize one bounded plain-text attribute.
	 *
	 * @param mixed $value Parsed attribute value.
	 */
	public static function text( mixed $value ): string {
		return is_string( $value ) ? substr( sanitize_text_field( $value ), 0, 120 ) : '';
	}
}
