<?php
/**
 * Bounded administrator occurrence repair.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Repairs one stable public-gap page while unresolved rows remain fail-closed.
 */
final readonly class OccurrenceRepairBatchProcessor {
	public const BATCH_SIZE = 25;

	/**
	 * Create the bounded processor.
	 *
	 * @param OccurrenceIndexRepairService $repairer Type-aware repair service.
	 * @param OccurrenceCoverageMetaQuery  $queries  Repair candidate query builder.
	 */
	public function __construct(
		private OccurrenceIndexRepairService $repairer = new OccurrenceIndexRepairer(),
		private OccurrenceCoverageMetaQuery $queries = new OccurrenceCoverageMetaQuery()
	) {}

	/**
	 * Repair one bounded candidate page after already unresolved candidates.
	 *
	 * @param int $unresolved_offset Number of earlier invalid or failed candidates.
	 */
	public function process( int $unresolved_offset = 0 ): OccurrenceIndexBatchResult {
		$today = wp_date( 'Y-m-d' );

		if ( ! is_string( $today ) ) {
			return new OccurrenceIndexBatchResult( 0, 0, 0, 1, false );
		}

		try {
			$meta_query = $this->queries->public_gaps( $today );
		} catch ( \InvalidArgumentException ) {
			return new OccurrenceIndexBatchResult( 0, 0, 0, 1, false );
		}

		$ids = get_posts(
			array(
				'post_type'              => EventPostType::POST_TYPE,
				'post_status'            => 'publish',
				'has_password'           => false,
				'fields'                 => 'ids',
				'posts_per_page'         => self::BATCH_SIZE,
				'offset'                 => max( 0, $unresolved_offset ),
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded administrator repair over plugin-owned single-value metadata.
			)
		);

		$indexed = 0;
		$invalid = 0;
		$failed  = 0;

		foreach ( $ids as $event_id ) {
			$post = get_post( $event_id );

			if ( ! $post instanceof WP_Post || EventPostType::POST_TYPE !== $post->post_type ) {
				++$failed;
				continue;
			}

			$result = $this->repairer->repair( $event_id, $post->post_status );

			if ( OccurrenceIndexRepairStatus::INDEXED === $result ) {
				++$indexed;
			} elseif ( OccurrenceIndexRepairStatus::INVALID === $result ) {
				++$invalid;
			} elseif ( OccurrenceIndexRepairStatus::FAILED === $result ) {
				++$failed;
			}
		}

		return new OccurrenceIndexBatchResult(
			count( $ids ),
			$indexed,
			$invalid,
			$failed,
			count( $ids ) === self::BATCH_SIZE
		);
	}
}
