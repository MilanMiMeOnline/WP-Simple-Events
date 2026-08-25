<?php
/**
 * WordPress public occurrence coverage probe.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Content\EventPostType;

/**
 * Finds one indexed public event whose projection is missing or explicitly dirty.
 */
final class WordPressOccurrenceCoverageProbe implements OccurrenceCoverageProbe {
	/**
	 * Create the WordPress coverage probe.
	 *
	 * @param OccurrenceCoverageMetaQuery $queries Coverage candidate query builder.
	 */
	public function __construct(
		private readonly OccurrenceCoverageMetaQuery $queries = new OccurrenceCoverageMetaQuery()
	) {}

	/**
	 * Perform one bounded, cacheable WordPress metadata query.
	 */
	public function has_public_gap(): bool {
		$today = wp_date( 'Y-m-d' );

		if ( ! is_string( $today ) ) {
			return true;
		}

		try {
			$meta_query = $this->queries->public_gaps( $today );
		} catch ( \InvalidArgumentException ) {
			return true;
		}

		$ids = get_posts(
			array(
				'post_type'              => EventPostType::POST_TYPE,
				'post_status'            => 'publish',
				'has_password'           => false,
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'paged'                  => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded readiness gate over plugin-owned single-value metadata; never loads more than one ID.
			)
		);

		return array() !== $ids;
	}
}
