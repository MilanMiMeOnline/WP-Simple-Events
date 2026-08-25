<?php
/**
 * Series-owned fields inherited by one occurrence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Carries normalized series values without exposing WordPress metadata keys.
 */
final readonly class RecurrenceOccurrenceInheritedFields {
	/**
	 * Store one normalized inheritance snapshot.
	 *
	 * @param string $title             Series title.
	 * @param string $note              Series note; empty while notes remain occurrence-only.
	 * @param int    $featured_image_id Series featured-image attachment ID, or zero.
	 * @param string $venue             Series venue.
	 * @param string $address           Series address.
	 * @param string $location_url      Series location URL.
	 * @param string $event_url         Series external event URL.
	 * @param string $event_url_label   Series external action label.
	 */
	public function __construct(
		public string $title,
		public string $note,
		public int $featured_image_id,
		public string $venue,
		public string $address,
		public string $location_url,
		public string $event_url,
		public string $event_url_label
	) {}
}
