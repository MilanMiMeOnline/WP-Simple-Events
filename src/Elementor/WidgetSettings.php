<?php
/**
 * Elementor widget settings adapter.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Query\EventQueryCriteria;

/**
 * Converts untrusted Elementor settings to native shortcode attributes.
 */
final class WidgetSettings {
	/**
	 * Normalize list/grid controls.
	 *
	 * @param array<string, mixed> $settings Elementor display settings.
	 * @return array<string, mixed>
	 */
	public static function event_list( array $settings ): array {
		return array(
			'view'          => self::choice( $settings['view'] ?? null, array( 'list', 'grid' ), 'grid' ),
			'period'        => self::choice( $settings['period'] ?? null, array( 'upcoming', 'past', 'all' ), 'upcoming' ),
			'limit'         => self::integer( $settings['limit'] ?? null, 12, 1, EventQueryCriteria::MAX_LIMIT ),
			'columns'       => self::integer( $settings['columns'] ?? null, 3, 1, 4 ),
			'category'      => self::slugs( $settings['category'] ?? array() ),
			'tag'           => self::slugs( $settings['tag'] ?? array() ),
			'filters'       => self::switcher( $settings, 'filters', false ),
			'pagination'    => self::switcher( $settings, 'pagination', true ),
			'show_excerpt'  => self::switcher( $settings, 'show_excerpt', true ),
			'show_image'    => self::switcher( $settings, 'show_image', true ),
			'show_location' => self::switcher( $settings, 'show_location', true ),
		);
	}

	/**
	 * Normalize calendar controls.
	 *
	 * @param array<string, mixed> $settings Elementor display settings.
	 * @return array<string, mixed>
	 */
	public static function calendar( array $settings ): array {
		return array(
			'initial_view' => self::choice( $settings['initial_view'] ?? null, array( 'month', 'list' ), 'month' ),
			'mobile_view'  => self::choice( $settings['mobile_view'] ?? null, array( 'month', 'list' ), 'list' ),
			'category'     => self::slugs( $settings['category'] ?? array() ),
			'tag'          => self::slugs( $settings['tag'] ?? array() ),
			'filters'      => self::switcher( $settings, 'filters', true ),
		);
	}

	/**
	 * Normalize the optional event selected for editor preview.
	 *
	 * @param array<string, mixed> $settings Elementor display settings.
	 * @return array<string, int>
	 */
	public static function details( array $settings ): array {
		$value = $settings['event_id'] ?? null;

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return array();
		}

		$string = trim( (string) $value );

		if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $string ) ) {
			return array();
		}

		$event_id = filter_var( $string, FILTER_VALIDATE_INT );

		return false === $event_id ? array() : array( 'id' => $event_id );
	}

	/**
	 * Select a scalar value from an explicit allowlist.
	 *
	 * @param mixed    $value    Raw setting.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Invalid-value fallback.
	 */
	private static function choice( mixed $value, array $allowed, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Normalize a bounded decimal integer without coercing other shapes.
	 *
	 * @param mixed $value    Raw setting.
	 * @param int   $fallback Invalid-value fallback.
	 * @param int   $minimum  Inclusive lower bound.
	 * @param int   $maximum  Inclusive upper bound.
	 */
	private static function integer( mixed $value, int $fallback, int $minimum, int $maximum ): int {
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
	 * Normalize an Elementor switcher using only its documented values.
	 *
	 * @param array<string, mixed> $settings Display settings.
	 * @param string               $key      Setting key.
	 * @param bool                 $fallback Missing or malformed fallback.
	 */
	private static function switcher( array $settings, string $key, bool $fallback ): bool {
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
	 * Sanitize, deduplicate and bound term selections.
	 *
	 * @param mixed $value Raw multiple selection.
	 * @return string[]
	 */
	private static function slugs( mixed $value ): array {
		$values = is_array( $value ) ? $value : array();
		$slugs  = array();

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
}
