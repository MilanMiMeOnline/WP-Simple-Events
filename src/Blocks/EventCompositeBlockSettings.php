<?php
/**
 * Primary Gutenberg event-component setting normalization.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

use MiMe\WPSimpleEvents\Query\EventQueryCriteria;

/** Maps schema-shaped block attributes to the established shortcode contracts. */
final class EventCompositeBlockSettings {
	/**
	 * Normalize Event List / Grid attributes.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @return array<string, mixed>
	 */
	public static function event_list( array $attributes ): array {
		return array(
			'view'           => self::choice( $attributes['view'] ?? null, array( 'list', 'grid' ), 'grid' ),
			'period'         => self::choice( $attributes['period'] ?? null, array( 'upcoming', 'past', 'all' ), 'upcoming' ),
			'limit'          => self::integer( $attributes['limit'] ?? null, 12, 1, EventQueryCriteria::MAX_LIMIT ),
			'columns'        => self::integer( $attributes['columns'] ?? null, 3, 1, 4 ),
			'category'       => self::slugs( $attributes['categories'] ?? null ),
			'tag'            => self::slugs( $attributes['tags'] ?? null ),
			'filters'        => self::boolean( $attributes, 'filters', false ),
			'pagination'     => self::boolean( $attributes, 'pagination', true ),
			'show_excerpt'   => self::boolean( $attributes, 'showExcerpt', true ),
			'show_image'     => self::boolean( $attributes, 'showImage', true ),
			'show_location'  => self::boolean( $attributes, 'showLocation', true ),
			'show_title'     => self::boolean( $attributes, 'showTitle', true ),
			'show_date'      => self::boolean( $attributes, 'showDate', true ),
			'excerpt_length' => self::integer( $attributes['excerptLength'] ?? null, 30, 1, 100 ),
			'heading_level'  => self::choice( $attributes['headingLevel'] ?? null, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3' ),
			...self::filter_presentation( $attributes, false ),
		);
	}

	/**
	 * Normalize Event Calendar attributes.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @return array<string, mixed>
	 */
	public static function calendar( array $attributes ): array {
		return array(
			'initial_view'           => self::choice( $attributes['initialView'] ?? null, array( 'month', 'list' ), 'month' ),
			'mobile_view'            => self::choice( $attributes['mobileView'] ?? null, array( 'month', 'list' ), 'list' ),
			'category'               => self::slugs( $attributes['categories'] ?? null ),
			'tag'                    => self::slugs( $attributes['tags'] ?? null ),
			'filters'                => self::boolean( $attributes, 'filters', true ),
			'initial_date'           => self::canonical_date( $attributes['initialDate'] ?? null ),
			'show_navigation'        => self::boolean( $attributes, 'showNavigation', true ),
			'show_today'             => self::boolean( $attributes, 'showToday', true ),
			'show_view_switcher'     => self::boolean( $attributes, 'showViewSwitcher', true ),
			'fallback_heading_level' => self::choice( $attributes['fallbackHeadingLevel'] ?? null, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3' ),
			...self::filter_presentation( $attributes, true ),
		);
	}

	/**
	 * Map shared filter-presentation block attributes to shortcode keys.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @param bool                 $default_results Host-compatible result default.
	 * @return array<string, bool|string>
	 */
	private static function filter_presentation( array $attributes, bool $default_results ): array {
		return array(
			'filter_categories'     => self::boolean( $attributes, 'filterCategories', true ),
			'filter_tags'           => self::boolean( $attributes, 'filterTags', true ),
			'filter_layout'         => self::choice( $attributes['filterLayout'] ?? null, array( 'auto', 'horizontal', 'stacked' ), 'auto' ),
			'filter_disclosure'     => self::choice( $attributes['filterDisclosure'] ?? null, array( 'auto', 'open', 'closed' ), 'auto' ),
			'filter_chips'          => self::boolean( $attributes, 'filterChips', true ),
			'filter_results'        => self::boolean( $attributes, 'filterResults', $default_results ),
			'filter_label'          => self::text( $attributes['filterLabel'] ?? null ),
			'filter_period_label'   => self::text( $attributes['filterPeriodLabel'] ?? null ),
			'filter_category_label' => self::text( $attributes['filterCategoryLabel'] ?? null ),
			'filter_tag_label'      => self::text( $attributes['filterTagLabel'] ?? null ),
			'filter_apply_label'    => self::text( $attributes['filterApplyLabel'] ?? null ),
		);
	}

	/**
	 * Normalize Event Details attributes to the shared shortcode contract.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @return array<string, mixed>
	 */
	public static function details( array $attributes ): array {
		return array(
			'show_title'       => self::boolean( $attributes, 'showTitle', true ),
			'show_image'       => self::boolean( $attributes, 'showImage', true ),
			'show_date'        => self::boolean( $attributes, 'showDate', true ),
			'show_status'      => self::boolean( $attributes, 'showStatus', true ),
			'show_location'    => self::boolean( $attributes, 'showLocation', true ),
			'show_content'     => self::boolean( $attributes, 'showContent', true ),
			'show_action'      => self::boolean( $attributes, 'showAction', true ),
			'show_terms'       => self::boolean( $attributes, 'showTerms', true ),
			'heading_level'    => self::choice( $attributes['headingLevel'] ?? null, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h1' ),
			'date_label'       => self::text( $attributes['dateLabel'] ?? null ),
			'venue_label'      => self::text( $attributes['venueLabel'] ?? null ),
			'location_label'   => self::text( $attributes['locationLabel'] ?? null ),
			'action_label'     => self::text( $attributes['actionLabel'] ?? null ),
			'categories_label' => self::text( $attributes['categoriesLabel'] ?? null ),
			'tags_label'       => self::text( $attributes['tagsLabel'] ?? null ),
		);
	}

	/**
	 * Normalize an explicit schema-shaped event identifier.
	 *
	 * @param mixed $value Parsed event identifier.
	 */
	public static function event_id( mixed $value ): ?int {
		return is_int( $value ) && $value > 0 ? $value : null;
	}

	/**
	 * Select one documented string value.
	 *
	 * @param mixed    $value     Parsed attribute value.
	 * @param string[] $allowlist Allowed values.
	 * @param string   $fallback  Invalid-value fallback.
	 */
	private static function choice( mixed $value, array $allowlist, string $fallback ): string {
		return is_string( $value ) && in_array( $value, $allowlist, true ) ? $value : $fallback;
	}

	/**
	 * Normalize a strictly typed bounded integer.
	 *
	 * @param mixed $value    Parsed attribute value.
	 * @param int   $fallback Invalid-value fallback.
	 * @param int   $minimum  Inclusive minimum.
	 * @param int   $maximum  Inclusive maximum.
	 */
	private static function integer( mixed $value, int $fallback, int $minimum, int $maximum ): int {
		return is_int( $value ) && $value >= $minimum && $value <= $maximum ? $value : $fallback;
	}

	/**
	 * Accept one real strictly typed YYYY-MM-DD block attribute.
	 *
	 * @param mixed $value Parsed date attribute.
	 */
	private static function canonical_date( mixed $value ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $matches ) ) {
			return '';
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
	}

	/**
	 * Normalize one bounded strictly typed plain-text block attribute.
	 *
	 * @param mixed $value Parsed text attribute.
	 */
	private static function text( mixed $value ): string {
		return is_string( $value ) ? substr( trim( sanitize_text_field( $value ) ), 0, 120 ) : '';
	}

	/**
	 * Normalize one strictly typed boolean.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @param string               $key        Attribute name.
	 * @param bool                 $fallback   Missing-value fallback.
	 */
	private static function boolean( array $attributes, string $key, bool $fallback ): bool {
		return is_bool( $attributes[ $key ] ?? null ) ? $attributes[ $key ] : $fallback;
	}

	/**
	 * Normalize, deduplicate and bound taxonomy slugs.
	 *
	 * @param mixed $value Parsed slug list.
	 * @return list<string>
	 */
	private static function slugs( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$slugs = array();

		foreach ( array_slice( $value, 0, 20 ) as $item ) {
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
