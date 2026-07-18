<?php
/**
 * Event publication completeness policy.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Defines WordPress states that require a complete event date range.
 */
final class EventPublicationPolicy {
	/**
	 * Whether this post status represents a published or scheduled event.
	 *
	 * @param string $post_status WordPress post status.
	 */
	public function requires_date_range( string $post_status ): bool {
		return in_array( $post_status, array( 'publish', 'future', 'private' ), true );
	}
}
