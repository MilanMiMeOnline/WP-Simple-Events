<?php
/**
 * Event archive slug conflict detection.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Routing;

use WP_Post;

/**
 * Detects an existing WordPress page at the configured archive path.
 */
final class EventArchiveSlugConflictDetector {
	/**
	 * Determine whether a non-trashed page occupies the archive slug.
	 *
	 * @param string $slug Validated archive slug.
	 */
	public function has_page_conflict( string $slug ): bool {
		$page = get_page_by_path( $slug );

		return $page instanceof WP_Post
			&& ! in_array( $page->post_status, array( 'trash', 'auto-draft' ), true );
	}
}
