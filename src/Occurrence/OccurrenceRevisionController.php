<?php
/**
 * Occurrence projection revision lifecycle.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;

/**
 * Invalidates derived occurrence rows whenever canonical event metadata is restored.
 */
final class OccurrenceRevisionController {
	/**
	 * Register the trusted WordPress revision lifecycle hook.
	 */
	public function register(): void {
		add_action( 'wp_restore_post_revision', array( $this, 'after_restore' ), 10, 2 );
	}

	/**
	 * Mark occurrence projection dirty after WordPress restores revisioned metadata.
	 *
	 * Core has already authorized and executed the revision restore before this
	 * lifecycle action. This callback changes only the plugin-owned derived-state
	 * health marker; it never modifies restored canonical data.
	 *
	 * @param int $post_id     Restored canonical post ID.
	 * @param int $revision_id Source revision ID.
	 */
	public function after_restore( int $post_id, int $revision_id ): void {
		unset( $revision_id );

		if ( $post_id <= 0 || EventPostType::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, EventMeta::INDEX_DIRTY, true );
	}
}
