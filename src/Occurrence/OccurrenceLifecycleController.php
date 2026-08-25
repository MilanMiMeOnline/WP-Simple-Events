<?php
/**
 * Occurrence lifecycle cleanup hooks.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Removes derived rows on permanent event deletion while preserving trash restore.
 */
final class OccurrenceLifecycleController {
	/**
	 * Create the lifecycle controller.
	 *
	 * @param OccurrenceProjectionStore $store Projection persistence boundary.
	 */
	public function __construct(
		private readonly OccurrenceProjectionStore $store = new WordPressOccurrenceProjectionStore()
	) {}

	/**
	 * Register permanent-deletion cleanup.
	 */
	public function register(): void {
		add_action( 'before_delete_post', array( $this, 'delete' ), 10, 2 );
	}

	/**
	 * Remove only rows owned by the event being permanently deleted.
	 *
	 * @param int          $post_id Event candidate ID.
	 * @param WP_Post|null $post    WordPress post being deleted.
	 */
	public function delete( int $post_id, ?WP_Post $post = null ): void {
		$post ??= get_post( $post_id );

		if ( ! $post instanceof WP_Post || EventPostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		$this->store->remove( $post_id );
	}
}
