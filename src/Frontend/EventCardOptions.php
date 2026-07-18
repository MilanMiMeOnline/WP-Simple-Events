<?php
/**
 * Event card display options.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Controls optional event-card sections for shortcodes and future widgets.
 */
final readonly class EventCardOptions {
	/**
	 * Store optional card section choices.
	 *
	 * @param bool $show_excerpt  Show the WordPress excerpt.
	 * @param bool $show_image    Show the featured image.
	 * @param bool $show_location Show venue or address.
	 */
	public function __construct(
		public bool $show_excerpt,
		public bool $show_image,
		public bool $show_location
	) {}
}
