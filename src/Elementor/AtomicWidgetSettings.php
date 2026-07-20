<?php
/**
 * Atomic Elementor widget setting normalization.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

/**
 * Validates stored Elementor values before they reach presentation services.
 */
final class AtomicWidgetSettings {
	/**
	 * Normalize an explicit event identifier.
	 *
	 * @param mixed $value Stored Elementor value.
	 */
	public static function event_id( mixed $value ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$string = trim( (string) $value );

		if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $string ) ) {
			return null;
		}

		$event_id = filter_var( $string, FILTER_VALIDATE_INT );

		return false === $event_id ? null : $event_id;
	}

	/**
	 * Select one documented string value.
	 *
	 * @param mixed    $value    Stored Elementor value.
	 * @param string[] $allowed  Allowlisted values.
	 * @param string   $fallback Invalid-value fallback.
	 */
	public static function choice( mixed $value, array $allowed, string $fallback ): string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Normalize an Elementor switcher using only documented values.
	 *
	 * @param array<string, mixed> $settings Stored display settings.
	 * @param string               $key      Setting identifier.
	 * @param bool                 $fallback Missing or malformed fallback.
	 */
	public static function switcher( array $settings, string $key, bool $fallback ): bool {
		if ( ! array_key_exists( $key, $settings ) ) {
			return $fallback;
		}

		return match ( $settings[ $key ] ) {
			'yes'   => true,
			''      => false,
			default => $fallback,
		};
	}

	/**
	 * Normalize one bounded plain-text control.
	 *
	 * @param mixed $value Stored Elementor value.
	 */
	public static function text( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return substr( sanitize_text_field( $value ), 0, 120 );
	}
}
