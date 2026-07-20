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
			'description'         => __( 'Events with a date, status and optional location.', 'simple-events-by-mime' ),
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
				'elementor',
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
			'name'                     => __( 'Events', 'simple-events-by-mime' ),
			'singular_name'            => __( 'Event', 'simple-events-by-mime' ),
			'menu_name'                => __( 'Events', 'simple-events-by-mime' ),
			'name_admin_bar'           => __( 'Event', 'simple-events-by-mime' ),
			'add_new'                  => __( 'Add New', 'simple-events-by-mime' ),
			'add_new_item'             => __( 'Add New Event', 'simple-events-by-mime' ),
			'new_item'                 => __( 'New Event', 'simple-events-by-mime' ),
			'edit_item'                => __( 'Edit Event', 'simple-events-by-mime' ),
			'view_item'                => __( 'View Event', 'simple-events-by-mime' ),
			'all_items'                => __( 'All Events', 'simple-events-by-mime' ),
			'search_items'             => __( 'Search Events', 'simple-events-by-mime' ),
			'parent_item_colon'        => __( 'Parent Events:', 'simple-events-by-mime' ),
			'not_found'                => __( 'No events found.', 'simple-events-by-mime' ),
			'not_found_in_trash'       => __( 'No events found in Trash.', 'simple-events-by-mime' ),
			'archives'                 => __( 'Event Archives', 'simple-events-by-mime' ),
			'attributes'               => __( 'Event Attributes', 'simple-events-by-mime' ),
			'insert_into_item'         => __( 'Insert into event', 'simple-events-by-mime' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this event', 'simple-events-by-mime' ),
			'featured_image'           => __( 'Event image', 'simple-events-by-mime' ),
			'set_featured_image'       => __( 'Set event image', 'simple-events-by-mime' ),
			'remove_featured_image'    => __( 'Remove event image', 'simple-events-by-mime' ),
			'use_featured_image'       => __( 'Use as event image', 'simple-events-by-mime' ),
			'filter_items_list'        => __( 'Filter events list', 'simple-events-by-mime' ),
			'items_list_navigation'    => __( 'Events list navigation', 'simple-events-by-mime' ),
			'items_list'               => __( 'Events list', 'simple-events-by-mime' ),
			'item_published'           => __( 'Event published.', 'simple-events-by-mime' ),
			'item_published_privately' => __( 'Event published privately.', 'simple-events-by-mime' ),
			'item_reverted_to_draft'   => __( 'Event reverted to draft.', 'simple-events-by-mime' ),
			'item_scheduled'           => __( 'Event scheduled.', 'simple-events-by-mime' ),
			'item_updated'             => __( 'Event updated.', 'simple-events-by-mime' ),
			'item_link'                => __( 'Event Link', 'simple-events-by-mime' ),
			'item_link_description'    => __( 'A link to an event.', 'simple-events-by-mime' ),
		);
	}
}
