<?php
/**
 * Resolved public event presentation data.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Domain\EventStatus;
use WP_Post;

/**
 * Provides named, normalized fields without exposing metadata keys to adapters.
 */
final readonly class EventPresentation {
	/**
	 * Store one request-local event presentation snapshot.
	 *
	 * @param WP_Post                    $event              WordPress event object.
	 * @param string                     $title              Visible title or translated fallback.
	 * @param string                     $permalink          Public event URL when available.
	 * @param bool                       $has_featured_image Whether a featured image exists.
	 * @param EventDatePresentation|null $date               Valid formatted date range.
	 * @param EventStatus|null           $status             Valid explicit event status.
	 * @param string                     $venue              Normalized venue.
	 * @param string                     $address            Normalized address.
	 * @param string                     $location_url       Valid HTTP(S) location URL.
	 * @param string                     $event_url          Valid HTTP(S) external action URL.
	 * @param string                     $event_url_label    Normalized custom action label.
	 * @param EventTermPresentation[]    $categories         Public category destinations.
	 * @param EventTermPresentation[]    $tags               Public tag destinations.
	 */
	public function __construct(
		public WP_Post $event,
		public string $title,
		public string $permalink,
		public bool $has_featured_image,
		public ?EventDatePresentation $date,
		public ?EventStatus $status,
		public string $venue,
		public string $address,
		public string $location_url,
		public string $event_url,
		public string $event_url_label,
		public array $categories,
		public array $tags
	) {}
}
