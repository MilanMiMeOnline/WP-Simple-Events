<?php
/**
 * Composite event-details presentation options.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/** Keeps the complete-details component bounded while atomic fields stay available. */
final readonly class EventDetailsOptions {
	/**
	 * Create one backward-compatible composite presentation.
	 *
	 * @param bool   $show_title      Show the event title.
	 * @param bool   $show_image      Show the featured image.
	 * @param bool   $show_date       Show the event date and time.
	 * @param bool   $show_status     Show the event status.
	 * @param bool   $show_location   Show venue and address information.
	 * @param bool   $show_content    Show the event body content.
	 * @param bool   $show_action     Show the external event action.
	 * @param bool   $show_terms      Show event categories and tags.
	 * @param string $heading_level   Allowlisted title heading element.
	 * @param string $date_label      Optional date label override.
	 * @param string $venue_label     Optional venue label override.
	 * @param string $location_label  Optional location-link label override.
	 * @param string $action_label    Optional action-link label override.
	 * @param string $categories_label Optional categories label override.
	 * @param string $tags_label      Optional tags label override.
	 */
	public function __construct(
		public bool $show_title = true,
		public bool $show_image = true,
		public bool $show_date = true,
		public bool $show_status = true,
		public bool $show_location = true,
		public bool $show_content = true,
		public bool $show_action = true,
		public bool $show_terms = true,
		public string $heading_level = 'h1',
		public string $date_label = '',
		public string $venue_label = '',
		public string $location_label = '',
		public string $action_label = '',
		public string $categories_label = '',
		public string $tags_label = ''
	) {}
}
