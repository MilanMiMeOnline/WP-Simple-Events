<?php
/**
 * Native event archive rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Domain\EventListView;
use MiMe\WPSimpleEvents\Shortcode\EventListAttributes;
use WP_Post;
use WP_Query;

/**
 * Presents the already-bounded native main archive query.
 */
final readonly class EventArchiveRenderer {
	/**
	 * Create the archive renderer.
	 *
	 * @param EventListRenderer    $events   Shared event collection renderer.
	 * @param EventArchiveControls $controls Native filter and pagination controls.
	 */
	public function __construct(
		private EventListRenderer $events = new EventListRenderer(),
		private EventArchiveControls $controls = new EventArchiveControls()
	) {}

	/**
	 * Render an archive without creating a second event query.
	 *
	 * @param WP_Query $query Native main event archive query.
	 */
	public function render( WP_Query $query ): string {
		$attributes = EventListAttributes::from_shortcode(
			array(
				'period'   => $query->get( 'wpse_period' ),
				'category' => $query->get( 'wpse_category' ),
				'tag'      => $query->get( 'wpse_tag' ),
			)
		);
		$posts      = array_values(
			array_filter( $query->posts, static fn ( mixed $post ): bool => $post instanceof WP_Post )
		);
		$page_value = $query->get( 'paged' );
		$page       = is_numeric( $page_value ) ? max( 1, (int) $page_value ) : 1;
		$title      = post_type_archive_title( '', false );

		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$title = __( 'Events', 'simple-events-by-mime' );
		}

		$output  = '<section class="wpse-events wpse-event-archive" aria-labelledby="wpse-event-archive-title">';
		$output .= '<h1 id="wpse-event-archive-title" class="wpse-event-archive-title">' . esc_html( $title ) . '</h1>';
		$output .= $this->controls->filters( $attributes );
		$output .= $this->events->render(
			$posts,
			EventListView::GRID,
			3,
			$attributes->card_options(),
			'wpse-archive-results'
		);
		$output .= $this->controls->pagination( $page, (int) $query->max_num_pages );

		return $output . '</section>';
	}
}
