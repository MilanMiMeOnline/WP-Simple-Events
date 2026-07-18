<?php
/**
 * Plugin deactivation routine.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Lifecycle;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;

/**
 * Removes transient rewrite state while preserving events and permissions.
 */
final class Deactivator {
	/**
	 * Remove the event type before rebuilding rewrite rules without its routes.
	 */
	public static function deactivate(): void {
		foreach ( array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				unregister_taxonomy( $taxonomy );
			}
		}

		if ( post_type_exists( EventPostType::POST_TYPE ) ) {
			unregister_post_type( EventPostType::POST_TYPE );
		}

		flush_rewrite_rules( false );
	}
}
