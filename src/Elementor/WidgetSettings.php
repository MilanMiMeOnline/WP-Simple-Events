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
			'view'           => self::choice( $settings['view'] ?? null, array( 'list', 'grid' ), 'grid' ),
			'period'         => self::choice( $settings['period'] ?? null, array( 'upcoming', 'past', 'all' ), 'upcoming' ),
			'limit'          => self::integer( $settings['limit'] ?? null, 12, 1, EventQueryCriteria::MAX_LIMIT ),
			'columns'        => self::integer( $settings['columns'] ?? null, 3, 1, 4 ),
			'category'       => self::slugs( $settings['category'] ?? array() ),
			'tag'            => self::slugs( $settings['tag'] ?? array() ),
			'filters'        => self::switcher( $settings, 'filters', false ),
			'pagination'     => self::switcher( $settings, 'pagination', true ),
			'show_excerpt'   => self::switcher( $settings, 'show_excerpt', true ),
			'show_image'     => self::switcher( $settings, 'show_image', true ),
			'show_location'  => self::switcher( $settings, 'show_location', true ),
			'show_title'     => self::switcher( $settings, 'show_title', true ),
			'show_date'      => self::switcher( $settings, 'show_date', true ),
			'excerpt_length' => self::integer( $settings['excerpt_length'] ?? null, 30, 1, 100 ),
			'heading_level'  => self::choice( $settings['heading_level'] ?? null, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3' ),
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
			'initial_view'           => self::choice( $settings['initial_view'] ?? null, array( 'month', 'list' ), 'month' ),
			'mobile_view'            => self::choice( $settings['mobile_view'] ?? null, array( 'month', 'list' ), 'list' ),
			'category'               => self::slugs( $settings['category'] ?? array() ),
			'tag'                    => self::slugs( $settings['tag'] ?? array() ),
			'filters'                => self::switcher( $settings, 'filters', true ),
			'initial_date'           => self::canonical_date( $settings['initial_date'] ?? null ),
			'show_navigation'        => self::switcher( $settings, 'show_navigation', true ),
			'show_today'             => self::switcher( $settings, 'show_today', true ),
			'show_view_switcher'     => self::switcher( $settings, 'show_view_switcher', true ),
			'fallback_heading_level' => self::choice( $settings['fallback_heading_level'] ?? null, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3' ),
		);
	}

	/**
	 * Normalize the optional event selected for editor preview.
	 *
	 * @param array<string, mixed> $settings Elementor display settings.
	 * @return array<string, bool|int|string>
	 */
	public static function details( array $settings ): array {
		$attributes = array(
			'show_title'       => self::switcher( $settings, 'show_title', true ),
			'show_image'       => self::switcher( $settings, 'show_image', true ),
			'show_date'        => self::switcher( $settings, 'show_date', true ),
			'show_status'      => self::switcher( $settings, 'show_status', true ),
			'show_location'    => self::switcher( $settings, 'show_location', true ),
			'show_content'     => self::switcher( $settings, 'show_content', true ),
			'show_action'      => self::switcher( $settings, 'show_action', true ),
			'show_terms'       => self::switcher( $settings, 'show_terms', true ),
			'heading_level'    => self::choice( $settings['heading_level'] ?? null, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h1' ),
			'date_label'       => self::text( $settings['date_label'] ?? null ),
			'venue_label'      => self::text( $settings['venue_label'] ?? null ),
			'location_label'   => self::text( $settings['location_label'] ?? null ),
			'action_label'     => self::text( $settings['action_label'] ?? null ),
			'categories_label' => self::text( $settings['categories_label'] ?? null ),
			'tags_label'       => self::text( $settings['tags_label'] ?? null ),
		);
		$value      = $settings['event_id'] ?? null;

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return $attributes;
		}

		$string = trim( (string) $value );

		if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $string ) ) {
			return $attributes;
		}

		$event_id = filter_var( $string, FILTER_VALIDATE_INT );

		if ( false !== $event_id ) {
			$attributes['id'] = $event_id;
		}

		return $attributes;
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
	 * Accept one real YYYY-MM-DD value without timezone interpretation.
	 *
	 * @param mixed $value Raw date control value.
	 */
	private static function canonical_date( mixed $value ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', trim( $value ), $matches ) ) {
			return '';
		}

		$value = trim( $value );

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
	}

	/**
	 * Normalize one bounded plain-text control.
	 *
	 * @param mixed $value Raw text control value.
	 */
	private static function text( mixed $value ): string {
		return is_string( $value ) ? substr( trim( sanitize_text_field( $value ) ), 0, 120 ) : '';
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
