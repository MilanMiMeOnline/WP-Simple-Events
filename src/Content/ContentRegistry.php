<?php
/**
 * Event content registration coordinator.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Content;

/**
 * Registers the post type, taxonomies and metadata in a stable order.
 */
final class ContentRegistry {
	/**
	 * Register the complete native event data model.
	 */
	public function register(): void {
		( new EventPostType() )->register();
		( new EventTaxonomies() )->register();
		( new EventMeta() )->register();
	}
}
