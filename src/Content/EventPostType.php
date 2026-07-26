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
			'description'         => __( 'Events with a date, status and optional location.', 'mime-simple-events-calendar' ),
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
			'name'                     => __( 'Events', 'mime-simple-events-calendar' ),
			'singular_name'            => __( 'Event', 'mime-simple-events-calendar' ),
			'menu_name'                => __( 'Events', 'mime-simple-events-calendar' ),
			'name_admin_bar'           => __( 'Event', 'mime-simple-events-calendar' ),
			'add_new'                  => __( 'Add New', 'mime-simple-events-calendar' ),
			'add_new_item'             => __( 'Add New Event', 'mime-simple-events-calendar' ),
			'new_item'                 => __( 'New Event', 'mime-simple-events-calendar' ),
			'edit_item'                => __( 'Edit Event', 'mime-simple-events-calendar' ),
			'view_item'                => __( 'View Event', 'mime-simple-events-calendar' ),
			'all_items'                => __( 'All Events', 'mime-simple-events-calendar' ),
			'search_items'             => __( 'Search Events', 'mime-simple-events-calendar' ),
			'parent_item_colon'        => __( 'Parent Events:', 'mime-simple-events-calendar' ),
			'not_found'                => __( 'No events found.', 'mime-simple-events-calendar' ),
			'not_found_in_trash'       => __( 'No events found in Trash.', 'mime-simple-events-calendar' ),
			'archives'                 => __( 'Event Archives', 'mime-simple-events-calendar' ),
			'attributes'               => __( 'Event Attributes', 'mime-simple-events-calendar' ),
			'insert_into_item'         => __( 'Insert into event', 'mime-simple-events-calendar' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this event', 'mime-simple-events-calendar' ),
			'featured_image'           => __( 'Event image', 'mime-simple-events-calendar' ),
			'set_featured_image'       => __( 'Set event image', 'mime-simple-events-calendar' ),
			'remove_featured_image'    => __( 'Remove event image', 'mime-simple-events-calendar' ),
			'use_featured_image'       => __( 'Use as event image', 'mime-simple-events-calendar' ),
			'filter_items_list'        => __( 'Filter events list', 'mime-simple-events-calendar' ),
			'items_list_navigation'    => __( 'Events list navigation', 'mime-simple-events-calendar' ),
			'items_list'               => __( 'Events list', 'mime-simple-events-calendar' ),
			'item_published'           => __( 'Event published.', 'mime-simple-events-calendar' ),
			'item_published_privately' => __( 'Event published privately.', 'mime-simple-events-calendar' ),
			'item_reverted_to_draft'   => __( 'Event reverted to draft.', 'mime-simple-events-calendar' ),
			'item_scheduled'           => __( 'Event scheduled.', 'mime-simple-events-calendar' ),
			'item_updated'             => __( 'Event updated.', 'mime-simple-events-calendar' ),
			'item_link'                => __( 'Event Link', 'mime-simple-events-calendar' ),
			'item_link_description'    => __( 'A link to an event.', 'mime-simple-events-calendar' ),
		);
	}
}
