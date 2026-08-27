<?php
/**
 * Normalized settings for composite Divi event modules.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

use MiMe\WPSimpleEvents\Query\EventQueryCriteria;

/** Maps nested Divi values to the established shortcode contracts. */
final class DiviCompositeSettings {
	/**
	 * Normalize Event Details settings.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 * @return array<string, bool|int|string>
	 */
	public static function details( array $attrs ): array {
		$settings = array(
			'show_title'       => DiviModuleSettings::toggle( $attrs, 'showTitle', true ),
			'show_image'       => DiviModuleSettings::toggle( $attrs, 'showImage', true ),
			'show_date'        => DiviModuleSettings::toggle( $attrs, 'showDate', true ),
			'show_status'      => DiviModuleSettings::toggle( $attrs, 'showStatus', true ),
			'show_location'    => DiviModuleSettings::toggle( $attrs, 'showLocation', true ),
			'show_content'     => DiviModuleSettings::toggle( $attrs, 'showContent', true ),
			'show_action'      => DiviModuleSettings::toggle( $attrs, 'showAction', true ),
			'show_terms'       => DiviModuleSettings::toggle( $attrs, 'showTerms', true ),
			'heading_level'    => DiviModuleSettings::choice( $attrs, 'headingLevel', array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h1' ),
			'date_label'       => DiviModuleSettings::text( $attrs, 'dateLabel' ),
			'venue_label'      => DiviModuleSettings::text( $attrs, 'venueLabel' ),
			'location_label'   => DiviModuleSettings::text( $attrs, 'locationLabel' ),
			'action_label'     => DiviModuleSettings::text( $attrs, 'actionLabel' ),
			'categories_label' => DiviModuleSettings::text( $attrs, 'categoriesLabel' ),
			'tags_label'       => DiviModuleSettings::text( $attrs, 'tagsLabel' ),
		);
		$event_id = DiviModuleSettings::event_id( $attrs );

		if ( $event_id > 0 ) {
			$settings['id'] = $event_id;
		}

		return $settings;
	}

	/**
	 * Normalize Event List / Grid settings.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 * @return array<string, mixed>
	 */
	public static function event_list( array $attrs ): array {
		return array(
			'view'           => DiviModuleSettings::choice( $attrs, 'view', array( 'list', 'grid' ), 'grid' ),
			'period'         => DiviModuleSettings::choice( $attrs, 'period', array( 'upcoming', 'past', 'all' ), 'upcoming' ),
			'limit'          => DiviModuleSettings::integer( $attrs, 'limit', 12, 1, EventQueryCriteria::MAX_LIMIT ),
			'columns'        => DiviModuleSettings::integer( $attrs, 'columns', 3, 1, 4 ),
			'category'       => DiviModuleSettings::slugs( $attrs, 'categories' ),
			'tag'            => DiviModuleSettings::slugs( $attrs, 'tags' ),
			'filters'        => DiviModuleSettings::toggle( $attrs, 'filters' ),
			'pagination'     => DiviModuleSettings::toggle( $attrs, 'pagination', true ),
			'show_excerpt'   => DiviModuleSettings::toggle( $attrs, 'showExcerpt', true ),
			'show_image'     => DiviModuleSettings::toggle( $attrs, 'showImage', true ),
			'show_location'  => DiviModuleSettings::toggle( $attrs, 'showLocation', true ),
			'show_title'     => DiviModuleSettings::toggle( $attrs, 'showTitle', true ),
			'show_date'      => DiviModuleSettings::toggle( $attrs, 'showDate', true ),
			'excerpt_length' => DiviModuleSettings::integer( $attrs, 'excerptLength', 30, 1, 100 ),
			'heading_level'  => DiviModuleSettings::choice( $attrs, 'headingLevel', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3' ),
		);
	}

	/**
	 * Normalize Event Calendar settings.
	 *
	 * @param array<string, mixed> $attrs Divi module attributes.
	 * @return array<string, mixed>
	 */
	public static function calendar( array $attrs ): array {
		return array(
			'initial_view'           => DiviModuleSettings::choice( $attrs, 'initialView', array( 'month', 'list' ), 'month' ),
			'mobile_view'            => DiviModuleSettings::choice( $attrs, 'mobileView', array( 'month', 'list' ), 'list' ),
			'category'               => DiviModuleSettings::slugs( $attrs, 'categories' ),
			'tag'                    => DiviModuleSettings::slugs( $attrs, 'tags' ),
			'filters'                => DiviModuleSettings::toggle( $attrs, 'filters', true ),
			'initial_date'           => DiviModuleSettings::canonical_date( $attrs, 'initialDate' ),
			'show_navigation'        => DiviModuleSettings::toggle( $attrs, 'showNavigation', true ),
			'show_today'             => DiviModuleSettings::toggle( $attrs, 'showToday', true ),
			'show_view_switcher'     => DiviModuleSettings::toggle( $attrs, 'showViewSwitcher', true ),
			'fallback_heading_level' => DiviModuleSettings::choice( $attrs, 'fallbackHeadingLevel', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3' ),
		);
	}
}
