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
			'description'        => __( 'Hierarchical categories used only by events.', 'mime-simple-events-calendar' ),
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
			'description'        => __( 'Non-hierarchical tags used only by events.', 'mime-simple-events-calendar' ),
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
			'name'              => __( 'Event Categories', 'mime-simple-events-calendar' ),
			'singular_name'     => __( 'Event Category', 'mime-simple-events-calendar' ),
			'search_items'      => __( 'Search Event Categories', 'mime-simple-events-calendar' ),
			'all_items'         => __( 'All Event Categories', 'mime-simple-events-calendar' ),
			'parent_item'       => __( 'Parent Event Category', 'mime-simple-events-calendar' ),
			'parent_item_colon' => __( 'Parent Event Category:', 'mime-simple-events-calendar' ),
			'edit_item'         => __( 'Edit Event Category', 'mime-simple-events-calendar' ),
			'view_item'         => __( 'View Event Category', 'mime-simple-events-calendar' ),
			'update_item'       => __( 'Update Event Category', 'mime-simple-events-calendar' ),
			'add_new_item'      => __( 'Add New Event Category', 'mime-simple-events-calendar' ),
			'new_item_name'     => __( 'New Event Category Name', 'mime-simple-events-calendar' ),
			'not_found'         => __( 'No event categories found.', 'mime-simple-events-calendar' ),
			'no_terms'          => __( 'No event categories', 'mime-simple-events-calendar' ),
			'back_to_items'     => __( 'Back to event categories', 'mime-simple-events-calendar' ),
			'menu_name'         => __( 'Event Categories', 'mime-simple-events-calendar' ),
		);
	}

	/**
	 * Return translated tag labels.
	 *
	 * @return array<string, string>
	 */
	private function tag_labels(): array {
		return array(
			'name'                       => __( 'Event Tags', 'mime-simple-events-calendar' ),
			'singular_name'              => __( 'Event Tag', 'mime-simple-events-calendar' ),
			'search_items'               => __( 'Search Event Tags', 'mime-simple-events-calendar' ),
			'popular_items'              => __( 'Popular Event Tags', 'mime-simple-events-calendar' ),
			'all_items'                  => __( 'All Event Tags', 'mime-simple-events-calendar' ),
			'edit_item'                  => __( 'Edit Event Tag', 'mime-simple-events-calendar' ),
			'view_item'                  => __( 'View Event Tag', 'mime-simple-events-calendar' ),
			'update_item'                => __( 'Update Event Tag', 'mime-simple-events-calendar' ),
			'add_new_item'               => __( 'Add New Event Tag', 'mime-simple-events-calendar' ),
			'new_item_name'              => __( 'New Event Tag Name', 'mime-simple-events-calendar' ),
			'separate_items_with_commas' => __( 'Separate event tags with commas', 'mime-simple-events-calendar' ),
			'add_or_remove_items'        => __( 'Add or remove event tags', 'mime-simple-events-calendar' ),
			'choose_from_most_used'      => __( 'Choose from the most used event tags', 'mime-simple-events-calendar' ),
			'not_found'                  => __( 'No event tags found.', 'mime-simple-events-calendar' ),
			'no_terms'                   => __( 'No event tags', 'mime-simple-events-calendar' ),
			'back_to_items'              => __( 'Back to event tags', 'mime-simple-events-calendar' ),
			'menu_name'                  => __( 'Event Tags', 'mime-simple-events-calendar' ),
		);
	}
}
