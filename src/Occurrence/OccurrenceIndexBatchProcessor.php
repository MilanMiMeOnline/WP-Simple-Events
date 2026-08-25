<?php
/**
 * Bounded existing-event occurrence indexing.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Repeatedly processes the first not-yet-attempted event page.
 */
final readonly class OccurrenceIndexBatchProcessor {
	public const BATCH_SIZE = 25;

	/**
	 * Create the bounded processor.
	 *
	 * @param OccurrenceIndexRepairService $repairer Canonical type-aware repair service.
	 */
	public function __construct(
		private OccurrenceIndexRepairService $repairer = new OccurrenceIndexRepairer()
	) {}

	/**
	 * Process one fixed page of events with no active generation or prior failure.
	 */
	public function process(): OccurrenceIndexBatchResult {
		$ids = get_posts(
			array(
				'post_type'              => EventPostType::POST_TYPE,
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'fields'                 => 'ids',
				'posts_per_page'         => self::BATCH_SIZE,
				'paged'                  => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded one-time migration over plugin-owned indexed metadata.
					'relation' => 'AND',
					array(
						'key'     => EventMeta::START_LOCAL,
						'value'   => '',
						'compare' => '!=',
					),
					array(
						'key'     => EventMeta::ACTIVE_GENERATION,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => EventMeta::INDEX_DIRTY,
						'compare' => 'NOT EXISTS',
					),
				),
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
