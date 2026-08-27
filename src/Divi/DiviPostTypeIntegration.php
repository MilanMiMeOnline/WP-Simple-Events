<?php
/**
 * Divi supported post-type integration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

use MiMe\WPSimpleEvents\Content\EventPostType;

/**
 * Exposes Events through Divi's own Post Type Integration preference.
 */
final class DiviPostTypeIntegration {
	/** Register the official Divi supported-third-party filter. */
	public function register(): void {
		add_filter( 'et_builder_third_party_post_types', array( $this, 'add_event_post_type' ) );
	}

	/**
	 * Add the event post type once without changing unrelated registrations.
	 *
	 * @param mixed $post_types Host-supplied post-type collection.
	 * @return array<int, string>
	 */
	public function add_event_post_type( mixed $post_types ): array {
		$normalized = is_array( $post_types )
			? array_values( array_filter( $post_types, 'is_string' ) )
			: array();

		if ( ! in_array( EventPostType::POST_TYPE, $normalized, true ) ) {
			$normalized[] = EventPostType::POST_TYPE;
		}

		return $normalized;
	}
}
