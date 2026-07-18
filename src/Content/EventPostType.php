<?php
/**
 * Event post type registration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Content;

use MiMe\WPSimpleEvents\Access\EventCapabilities;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;

/**
 * Registers the native WordPress event content type.
 */
final class EventPostType {
	public const POST_TYPE = 'wpse_event';

	/**
	 * Create the event post type definition.
	 *
	 * @param EventArchiveSettings $archive Validated archive settings.
	 */
	public function __construct( private readonly EventArchiveSettings $archive = new EventArchiveSettings() ) {}

	/**
	 * Register the event post type.
	 */
	public function register(): void {
		register_post_type( self::POST_TYPE, $this->arguments() );
	}

	/**
	 * Build the event post type arguments.
	 *
	 * @return array<string, mixed>
	 */
	public function arguments(): array {
		$archive_slug = $this->archive->slug();

		return array(
			'labels'              => $this->labels(),
			'description'         => __( 'Events with a date, status and optional location.', 'wp-simple-events' ),
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-calendar-alt',
			'hierarchical'        => false,
			'has_archive'         => $archive_slug,
			'rewrite'             => array(
				'slug'       => $archive_slug,
				'with_front' => false,
				'feeds'      => true,
				'pages'      => true,
			),
			'query_var'           => self::POST_TYPE,
			'can_export'          => true,
			'delete_with_user'    => false,
			'capability_type'     => array( 'wpse_event', 'wpse_events' ),
			'capabilities'        => EventCapabilities::post_type_map(),
			'map_meta_cap'        => true,
			'taxonomies'          => array(
				EventTaxonomies::CATEGORY,
				EventTaxonomies::TAG,
			),
			'supports'            => array(
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'author',
				'revisions',
				'custom-fields',
			),
		);
	}

	/**
	 * Return translated post type labels.
	 *
	 * @return array<string, string>
	 */
	private function labels(): array {
		return array(
			'name'                     => __( 'Events', 'wp-simple-events' ),
			'singular_name'            => __( 'Event', 'wp-simple-events' ),
			'menu_name'                => __( 'Events', 'wp-simple-events' ),
			'name_admin_bar'           => __( 'Event', 'wp-simple-events' ),
			'add_new'                  => __( 'Add New', 'wp-simple-events' ),
			'add_new_item'             => __( 'Add New Event', 'wp-simple-events' ),
			'new_item'                 => __( 'New Event', 'wp-simple-events' ),
			'edit_item'                => __( 'Edit Event', 'wp-simple-events' ),
			'view_item'                => __( 'View Event', 'wp-simple-events' ),
			'all_items'                => __( 'All Events', 'wp-simple-events' ),
			'search_items'             => __( 'Search Events', 'wp-simple-events' ),
			'parent_item_colon'        => __( 'Parent Events:', 'wp-simple-events' ),
			'not_found'                => __( 'No events found.', 'wp-simple-events' ),
			'not_found_in_trash'       => __( 'No events found in Trash.', 'wp-simple-events' ),
			'archives'                 => __( 'Event Archives', 'wp-simple-events' ),
			'attributes'               => __( 'Event Attributes', 'wp-simple-events' ),
			'insert_into_item'         => __( 'Insert into event', 'wp-simple-events' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this event', 'wp-simple-events' ),
			'featured_image'           => __( 'Event image', 'wp-simple-events' ),
			'set_featured_image'       => __( 'Set event image', 'wp-simple-events' ),
			'remove_featured_image'    => __( 'Remove event image', 'wp-simple-events' ),
			'use_featured_image'       => __( 'Use as event image', 'wp-simple-events' ),
			'filter_items_list'        => __( 'Filter events list', 'wp-simple-events' ),
			'items_list_navigation'    => __( 'Events list navigation', 'wp-simple-events' ),
			'items_list'               => __( 'Events list', 'wp-simple-events' ),
			'item_published'           => __( 'Event published.', 'wp-simple-events' ),
			'item_published_privately' => __( 'Event published privately.', 'wp-simple-events' ),
			'item_reverted_to_draft'   => __( 'Event reverted to draft.', 'wp-simple-events' ),
			'item_scheduled'           => __( 'Event scheduled.', 'wp-simple-events' ),
			'item_updated'             => __( 'Event updated.', 'wp-simple-events' ),
			'item_link'                => __( 'Event Link', 'wp-simple-events' ),
			'item_link_description'    => __( 'A link to an event.', 'wp-simple-events' ),
		);
	}
}
