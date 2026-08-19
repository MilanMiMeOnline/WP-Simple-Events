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
	 * Whether the excerpt is rendered.
	 *
	 * @var bool
	 */
	public bool $show_excerpt;
	/**
	 * Whether the featured image is rendered.
	 *
	 * @var bool
	 */
	public bool $show_image;
	/**
	 * Whether venue or address information is rendered.
	 *
	 * @var bool
	 */
	public bool $show_location;
	/**
	 * Whether the linked event title is rendered.
	 *
	 * @var bool
	 */
	public bool $show_title;
	/**
	 * Whether the localized date is rendered.
	 *
	 * @var bool
	 */
	public bool $show_date;
	/**
	 * Maximum number of excerpt words.
	 *
	 * @var int
	 */
	public int $excerpt_length;
	/**
	 * Allowlisted card-title heading element.
	 *
	 * @var string
	 */
	public string $heading_level;

	/**
	 * Store optional card section choices.
	 *
	 * @param bool   $show_excerpt  Show the WordPress excerpt.
	 * @param bool   $show_image    Show the featured image.
	 * @param bool   $show_location  Show venue or address.
	 * @param bool   $show_title     Show the linked event title.
	 * @param bool   $show_date      Show the localized event date.
	 * @param int    $excerpt_length Maximum excerpt words.
	 * @param string $heading_level  Allowlisted card-title heading element.
	 */
	public function __construct(
		bool $show_excerpt,
		bool $show_image,
		bool $show_location,
		bool $show_title = true,
		bool $show_date = true,
		int $excerpt_length = 30,
		string $heading_level = 'h3'
	) {
		$this->show_excerpt   = $show_excerpt;
		$this->show_image     = $show_image;
		$this->show_location  = $show_location;
		$this->show_title     = $show_title;
		$this->show_date      = $show_date;
		$this->excerpt_length = min( 100, max( 1, $excerpt_length ) );
		$this->heading_level  = in_array( $heading_level, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true )
			? $heading_level
			: 'h3';
	}
}
