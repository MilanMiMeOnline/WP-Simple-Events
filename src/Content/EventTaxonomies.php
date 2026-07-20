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
			'description'        => __( 'Hierarchical categories used only by events.', 'simple-events-by-mime' ),
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
			'description'        => __( 'Non-hierarchical tags used only by events.', 'simple-events-by-mime' ),
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
			'name'              => __( 'Event Categories', 'simple-events-by-mime' ),
			'singular_name'     => __( 'Event Category', 'simple-events-by-mime' ),
			'search_items'      => __( 'Search Event Categories', 'simple-events-by-mime' ),
			'all_items'         => __( 'All Event Categories', 'simple-events-by-mime' ),
			'parent_item'       => __( 'Parent Event Category', 'simple-events-by-mime' ),
			'parent_item_colon' => __( 'Parent Event Category:', 'simple-events-by-mime' ),
			'edit_item'         => __( 'Edit Event Category', 'simple-events-by-mime' ),
			'view_item'         => __( 'View Event Category', 'simple-events-by-mime' ),
			'update_item'       => __( 'Update Event Category', 'simple-events-by-mime' ),
			'add_new_item'      => __( 'Add New Event Category', 'simple-events-by-mime' ),
			'new_item_name'     => __( 'New Event Category Name', 'simple-events-by-mime' ),
			'not_found'         => __( 'No event categories found.', 'simple-events-by-mime' ),
			'no_terms'          => __( 'No event categories', 'simple-events-by-mime' ),
			'back_to_items'     => __( 'Back to event categories', 'simple-events-by-mime' ),
			'menu_name'         => __( 'Event Categories', 'simple-events-by-mime' ),
		);
	}

	/**
	 * Return translated tag labels.
	 *
	 * @return array<string, string>
	 */
	private function tag_labels(): array {
		return array(
			'name'                       => __( 'Event Tags', 'simple-events-by-mime' ),
			'singular_name'              => __( 'Event Tag', 'simple-events-by-mime' ),
			'search_items'               => __( 'Search Event Tags', 'simple-events-by-mime' ),
			'popular_items'              => __( 'Popular Event Tags', 'simple-events-by-mime' ),
			'all_items'                  => __( 'All Event Tags', 'simple-events-by-mime' ),
			'edit_item'                  => __( 'Edit Event Tag', 'simple-events-by-mime' ),
			'view_item'                  => __( 'View Event Tag', 'simple-events-by-mime' ),
			'update_item'                => __( 'Update Event Tag', 'simple-events-by-mime' ),
			'add_new_item'               => __( 'Add New Event Tag', 'simple-events-by-mime' ),
			'new_item_name'              => __( 'New Event Tag Name', 'simple-events-by-mime' ),
			'separate_items_with_commas' => __( 'Separate event tags with commas', 'simple-events-by-mime' ),
			'add_or_remove_items'        => __( 'Add or remove event tags', 'simple-events-by-mime' ),
			'choose_from_most_used'      => __( 'Choose from the most used event tags', 'simple-events-by-mime' ),
			'not_found'                  => __( 'No event tags found.', 'simple-events-by-mime' ),
			'no_terms'                   => __( 'No event tags', 'simple-events-by-mime' ),
			'back_to_items'              => __( 'Back to event tags', 'simple-events-by-mime' ),
			'menu_name'                  => __( 'Event Tags', 'simple-events-by-mime' ),
		);
	}
}
