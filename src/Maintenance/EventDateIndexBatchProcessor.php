<?php
/**
 * Bounded event date-index batch processing.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Maintenance;

use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Inspects a fixed event page and delegates per-event UTC repair.
 */
final readonly class EventDateIndexBatchProcessor {
	public const BATCH_SIZE = 50;

	/**
	 * Create the batch processor.
	 *
	 * @param EventDateIndexRepairer $repairer UTC-only repair service.
	 */
	public function __construct( private EventDateIndexRepairer $repairer = new EventDateIndexRepairer() ) {}

	/**
	 * Process one bounded, stable ID-ordered page.
	 *
	 * @param int $page One-based maintenance page.
	 */
	public function process( int $page ): EventDateIndexBatchResult {
		$page = max( 1, $page );
		$ids  = get_posts(
			array(
				'post_type'              => EventPostType::POST_TYPE,
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'fields'                 => 'ids',
				'posts_per_page'         => self::BATCH_SIZE,
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$changed = 0;
		$skipped = 0;
		$failed  = 0;

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post || EventPostType::POST_TYPE !== $post->post_type ) {
				++$failed;
				continue;
			}

			$result = $this->repairer->repair( $post_id, $post->post_status );

			if ( in_array( $result, array( EventDateIndexRepairStatus::REPAIRED, EventDateIndexRepairStatus::CLEARED ), true ) ) {
				++$changed;
			} elseif ( EventDateIndexRepairStatus::INVALID === $result ) {
				++$skipped;
			} elseif ( EventDateIndexRepairStatus::FAILED === $result ) {
				++$failed;
			}
		}

		return new EventDateIndexBatchResult(
			count( $ids ),
			$changed,
			$skipped,
			$failed,
			count( $ids ) === self::BATCH_SIZE,
			$page + 1
		);
	}
}
