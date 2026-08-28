<?php
/**
 * Normalized Divi module settings.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

/**
 * Reads nested Divi attributes through strict, host-neutral allowlists.
 */
final class DiviModuleSettings {
	/**
	 * Return the selected public event ID, or zero for the current context.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 */
	public static function event_id( array $attrs ): int {
		$value = self::inner_value( $attrs, 'eventId', '0' );

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return 0;
		}

		$string = trim( (string) $value );

		if ( '0' === $string ) {
			return 0;
		}

		if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $string ) ) {
			return 0;
		}

		$event_id = filter_var( $string, FILTER_VALIDATE_INT );

		return false === $event_id ? 0 : $event_id;
	}

	/**
	 * Return an allowlisted title heading.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 */
	public static function heading( array $attrs ): string {
		$heading = $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? 'h2';

		return is_string( $heading ) && in_array( $heading, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true )
			? $heading
			: 'h2';
	}

	/**
	 * Determine whether the title should link to its public destination.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 */
	public static function link_title( array $attrs ): bool {
		return self::toggle( $attrs, 'linkTitle' );
	}

	/**
	 * Determine whether one documented Divi toggle is enabled.
	 *
	 * @param array<string, mixed> $attrs    Divi module attributes.
	 * @param string               $key      Internal allowlisted setting key.
	 * @param bool                 $fallback Missing or malformed fallback.
	 */
	public static function toggle( array $attrs, string $key, bool $fallback = false ): bool {
		$value = self::inner_value( $attrs, $key, $fallback ? 'on' : 'off' );

		return match ( $value ) {
			'on'    => true,
			'off'   => false,
			default => $fallback,
		};
	}

	/**
	 * Select one documented string value.
	 *
	 * @param array<string, mixed> $attrs    Divi module attributes.
	 * @param string               $key      Internal allowlisted setting key.
	 * @param string[]             $allowed  Allowed values.
	 * @param string               $fallback Invalid-value fallback.
	 */
	public static function choice( array $attrs, string $key, array $allowed, string $fallback ): string {
		$value = self::inner_value( $attrs, $key, $fallback );

		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Normalize one bounded plain-text module value.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 * @param string               $key   Internal allowlisted setting key.
	 */
	public static function text( array $attrs, string $key ): string {
		$value = self::inner_value( $attrs, $key, '' );

		return is_string( $value ) ? substr( sanitize_text_field( $value ), 0, 120 ) : '';
	}

	/**
	 * Return one strict six-digit hexadecimal color or an empty value.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 * @param string               $key   Internal allowlisted setting key.
	 */
	public static function color( array $attrs, string $key ): string {
		$value = self::inner_value( $attrs, $key, '' );

		return is_string( $value ) && 1 === preg_match( '/^#[0-9a-f]{6}$/Di', $value ) ? strtolower( $value ) : '';
	}

	/**
	 * Return one optional bounded integer, preserving an unset editor value.
	 *
	 * @param array<string, mixed> $attrs   Divi module attributes.
	 * @param string               $key     Internal allowlisted setting key.
	 * @param int                  $minimum Inclusive lower bound.
	 * @param int                  $maximum Inclusive upper bound.
	 */
	public static function optional_integer( array $attrs, string $key, int $minimum, int $maximum ): ?int {
		$value = self::inner_value( $attrs, $key, null );

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$string = trim( (string) $value );

		if ( 1 !== preg_match( '/^[0-9]+$/D', $string ) ) {
			return null;
		}

		$integer = filter_var( $string, FILTER_VALIDATE_INT );

		return false !== $integer && $integer >= $minimum && $integer <= $maximum ? $integer : null;
	}

	/**
	 * Normalize one bounded decimal integer without coercing other shapes.
	 *
	 * @param array<string, mixed> $attrs    Divi module attributes.
	 * @param string               $key      Internal allowlisted setting key.
	 * @param int                  $fallback Invalid-value fallback.
	 * @param int                  $minimum  Inclusive lower bound.
	 * @param int                  $maximum  Inclusive upper bound.
	 */
	public static function integer( array $attrs, string $key, int $fallback, int $minimum, int $maximum ): int {
		$value = self::inner_value( $attrs, $key, $fallback );

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return $fallback;
		}

		$string = trim( (string) $value );

		if ( 1 !== preg_match( '/^[0-9]+$/D', $string ) ) {
			return $fallback;
		}

		$integer = filter_var( $string, FILTER_VALIDATE_INT );

		return false !== $integer && $integer >= $minimum && $integer <= $maximum ? $integer : $fallback;
	}

	/**
	 * Accept one real YYYY-MM-DD value without timezone interpretation.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 * @param string               $key   Internal allowlisted setting key.
	 */
	public static function canonical_date( array $attrs, string $key ): string {
		$value = self::inner_value( $attrs, $key, '' );

		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', trim( $value ), $matches ) ) {
			return '';
		}

		$value = trim( $value );

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
	}

	/**
	 * Normalize Divi checkbox values to a bounded set of taxonomy slugs.
	 *
	 * Divi currently stores checkbox fields as a list, while the associative
	 * branch keeps the public boundary fail-safe across compatible host updates.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 * @param string               $key   Internal allowlisted setting key.
	 * @return list<string>
	 */
	public static function slugs( array $attrs, string $key ): array {
		$value  = self::inner_value( $attrs, $key, array() );
		$values = array();

		if ( is_string( $value ) ) {
			$values = explode( ',', $value );
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $candidate_key => $candidate_value ) {
				if ( is_int( $candidate_key ) ) {
					$values[] = $candidate_value;
				} elseif ( true === $candidate_value || 'on' === $candidate_value || 1 === $candidate_value || '1' === $candidate_value ) {
					$values[] = $candidate_key;
				}
			}
		}

		$slugs = array();

		foreach ( array_slice( $values, 0, 20 ) as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}

			$slug = sanitize_title( $item );

			if ( '' !== $slug ) {
				$slugs[ $slug ] = $slug;
			}
		}

		return array_values( $slugs );
	}

	/**
	 * Read one non-responsive value from the module's event group.
	 *
	 * @param array<string, mixed> $attrs    Divi module attributes.
	 * @param string               $key      Allowlisted setting key.
	 * @param mixed                $fallback Safe fallback value.
	 */
	private static function inner_value( array $attrs, string $key, mixed $fallback ): mixed {
		return $attrs['event']['innerContent']['desktop']['value'][ $key ] ?? $fallback;
	}
}
