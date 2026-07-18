<?php
/**
 * Event taxonomy registration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Content;

use MiMe\WPSimpleEvents\Access\EventCapabilities;

/**
 * Registers event-specific categories and tags.
 */
final class EventTaxonomies {
	public const CATEGORY = 'wpse_event_category';
	public const TAG      = 'wpse_event_tag';

	/**
	 * Register both event taxonomies and their post-type relationship.
	 */
	public function register(): void {
		register_taxonomy( self::CATEGORY, EventPostType::POST_TYPE, $this->category_arguments() );
		register_taxonomy_for_object_type( self::CATEGORY, EventPostType::POST_TYPE );

		register_taxonomy( self::TAG, EventPostType::POST_TYPE, $this->tag_arguments() );
		register_taxonomy_for_object_type( self::TAG, EventPostType::POST_TYPE );
	}

	/**
	 * Build category taxonomy arguments.
	 *
	 * @return array<string, mixed>
	 */
	public function category_arguments(): array {
		return array(
			'labels'             => $this->category_labels(),
			'description'        => __( 'Hierarchical categories used only by events.', 'wp-simple-events' ),
			'public'             => true,
			'publicly_queryable' => true,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'capabilities'       => EventCapabilities::taxonomy_map(),
			'rewrite'            => array(
				'slug'         => 'event-category',
				'with_front'   => false,
				'hierarchical' => true,
			),
			'query_var'          => self::CATEGORY,
		);
	}

	/**
	 * Build tag taxonomy arguments.
	 *
	 * @return array<string, mixed>
	 */
	public function tag_arguments(): array {
		return array(
			'labels'             => $this->tag_labels(),
			'description'        => __( 'Non-hierarchical tags used only by events.', 'wp-simple-events' ),
			'public'             => true,
			'publicly_queryable' => true,
			'hierarchical'       => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_admin_column'  => false,
			'show_in_rest'       => true,
			'capabilities'       => EventCapabilities::taxonomy_map(),
			'rewrite'            => array(
				'slug'       => 'event-tag',
				'with_front' => false,
			),
			'query_var'          => self::TAG,
		);
	}

	/**
	 * Return translated category labels.
	 *
	 * @return array<string, string>
	 */
	private function category_labels(): array {
		return array(
			'name'              => __( 'Event Categories', 'wp-simple-events' ),
			'singular_name'     => __( 'Event Category', 'wp-simple-events' ),
			'search_items'      => __( 'Search Event Categories', 'wp-simple-events' ),
			'all_items'         => __( 'All Event Categories', 'wp-simple-events' ),
			'parent_item'       => __( 'Parent Event Category', 'wp-simple-events' ),
			'parent_item_colon' => __( 'Parent Event Category:', 'wp-simple-events' ),
			'edit_item'         => __( 'Edit Event Category', 'wp-simple-events' ),
			'view_item'         => __( 'View Event Category', 'wp-simple-events' ),
			'update_item'       => __( 'Update Event Category', 'wp-simple-events' ),
			'add_new_item'      => __( 'Add New Event Category', 'wp-simple-events' ),
			'new_item_name'     => __( 'New Event Category Name', 'wp-simple-events' ),
			'not_found'         => __( 'No event categories found.', 'wp-simple-events' ),
			'no_terms'          => __( 'No event categories', 'wp-simple-events' ),
			'back_to_items'     => __( 'Back to event categories', 'wp-simple-events' ),
			'menu_name'         => __( 'Event Categories', 'wp-simple-events' ),
		);
	}

	/**
	 * Return translated tag labels.
	 *
	 * @return array<string, string>
	 */
	private function tag_labels(): array {
		return array(
			'name'                       => __( 'Event Tags', 'wp-simple-events' ),
			'singular_name'              => __( 'Event Tag', 'wp-simple-events' ),
			'search_items'               => __( 'Search Event Tags', 'wp-simple-events' ),
			'popular_items'              => __( 'Popular Event Tags', 'wp-simple-events' ),
			'all_items'                  => __( 'All Event Tags', 'wp-simple-events' ),
			'edit_item'                  => __( 'Edit Event Tag', 'wp-simple-events' ),
			'view_item'                  => __( 'View Event Tag', 'wp-simple-events' ),
			'update_item'                => __( 'Update Event Tag', 'wp-simple-events' ),
			'add_new_item'               => __( 'Add New Event Tag', 'wp-simple-events' ),
			'new_item_name'              => __( 'New Event Tag Name', 'wp-simple-events' ),
			'separate_items_with_commas' => __( 'Separate event tags with commas', 'wp-simple-events' ),
			'add_or_remove_items'        => __( 'Add or remove event tags', 'wp-simple-events' ),
			'choose_from_most_used'      => __( 'Choose from the most used event tags', 'wp-simple-events' ),
			'not_found'                  => __( 'No event tags found.', 'wp-simple-events' ),
			'no_terms'                   => __( 'No event tags', 'wp-simple-events' ),
			'back_to_items'              => __( 'Back to event tags', 'wp-simple-events' ),
			'menu_name'                  => __( 'Event Tags', 'wp-simple-events' ),
		);
	}
}
