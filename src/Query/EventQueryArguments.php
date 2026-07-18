<?php
/**
 * WordPress event query argument builder.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Query;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventPeriod;

/**
 * Translates validated criteria into a bounded public WP_Query.
 */
final class EventQueryArguments {
	/**
	 * Build public event query arguments.
	 *
	 * @param EventQueryCriteria $criteria Validated query criteria.
	 * @return array<string, mixed>
	 */
	public function build( EventQueryCriteria $criteria ): array {
		$arguments = array(
			'post_type'              => EventPostType::POST_TYPE,
			'post_status'            => 'publish',
			'has_password'           => false,
			'posts_per_page'         => $criteria->limit,
			'paged'                  => $criteria->page,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'meta_key'               => EventMeta::START_UTC,
			'orderby'                => 'meta_value_num',
			'order'                  => EventPeriod::PAST === $criteria->period ? 'DESC' : 'ASC',
		);

		$meta_query = $this->meta_query( $criteria );

		if ( array() !== $meta_query ) {
			$arguments['meta_query'] = $meta_query;
		}

		$tax_query = $this->tax_query( $criteria->category_slugs, $criteria->tag_slugs );

		if ( array() !== $tax_query ) {
			$arguments['tax_query'] = $tax_query;
		}

		return $arguments;
	}

	/**
	 * Build a public query for events overlapping one half-open window.
	 *
	 * @param EventWindowCriteria $criteria Validated calendar criteria.
	 * @return array<string, mixed>
	 */
	public function build_window( EventWindowCriteria $criteria ): array {
		$arguments = array(
			'post_type'              => EventPostType::POST_TYPE,
			'post_status'            => 'publish',
			'has_password'           => false,
			'posts_per_page'         => $criteria->limit,
			'paged'                  => $criteria->page,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'meta_key'               => EventMeta::START_UTC,
			'orderby'                => 'meta_value_num',
			'order'                  => 'ASC',
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'     => EventMeta::END_UTC,
					'value'   => $criteria->window->start_utc,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => EventMeta::START_UTC,
					'value'   => $criteria->window->end_exclusive_utc,
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			),
		);
		$tax_query = $this->tax_query( $criteria->category_slugs, $criteria->tag_slugs );

		if ( array() !== $tax_query ) {
			$arguments['tax_query'] = $tax_query;
		}

		return $arguments;
	}

	/**
	 * Build the inclusive active/past boundary.
	 *
	 * @param EventQueryCriteria $criteria Validated query criteria.
	 * @return array<int, array<string, int|string>>
	 */
	private function meta_query( EventQueryCriteria $criteria ): array {
		if ( EventPeriod::ALL === $criteria->period ) {
			return array();
		}

		return array(
			array(
				'key'     => EventMeta::END_UTC,
				'value'   => $criteria->now_utc,
				'compare' => EventPeriod::UPCOMING === $criteria->period ? '>=' : '<',
				'type'    => 'NUMERIC',
			),
		);
	}

	/**
	 * Build event-specific category and tag filters.
	 *
	 * @param string[] $category_slugs Event category slugs.
	 * @param string[] $tag_slugs      Event tag slugs.
	 * @return array<int|string, array<string, mixed>|string>
	 */
	private function tax_query( array $category_slugs, array $tag_slugs ): array {
		$query = array();

		if ( array() !== $category_slugs ) {
			$query[] = array(
				'taxonomy' => EventTaxonomies::CATEGORY,
				'field'    => 'slug',
				'terms'    => $category_slugs,
				'operator' => 'IN',
			);
		}

		if ( array() !== $tag_slugs ) {
			$query[] = array(
				'taxonomy' => EventTaxonomies::TAG,
				'field'    => 'slug',
				'terms'    => $tag_slugs,
				'operator' => 'IN',
			);
		}

		if ( count( $query ) > 1 ) {
			$query['relation'] = 'AND';
		}

		return $query;
	}
}
