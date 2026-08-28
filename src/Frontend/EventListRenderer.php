<?php
/**
 * Event collection rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Domain\EventListView;
use WP_Post;

/**
 * Renders a list or grid from explicit event objects.
 */
final readonly class EventListRenderer {
	/**
	 * Create the collection renderer.
	 *
	 * @param EventRenderer $events Reusable event-card renderer.
	 */
	public function __construct( private EventRenderer $events = new EventRenderer() ) {}

	/**
	 * Render an event collection or its accessible empty state.
	 *
	 * @param WP_Post[]                                                      $posts      Public event posts.
	 * @param EventListView                                                  $view       List or grid layout.
	 * @param int                                                            $columns    Desktop grid columns.
	 * @param EventCardOptions                                               $options    Card section choices.
	 * @param string                                                         $results_id Stable instance results ID.
	 * @param array<int, \MiMe\WPSimpleEvents\Domain\EventColorPresentation> $colors Canonical event colors.
	 */
	public function render(
		array $posts,
		EventListView $view,
		int $columns,
		EventCardOptions $options,
		string $results_id,
		array $colors = array()
	): string {
		$cards = array();

		foreach ( $posts as $post ) {
			$card = $this->events->card( $post, $options, $colors[ $post->ID ] ?? null );

			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}

		return $this->collection( $cards, $view, $columns, $results_id );
	}

	/**
	 * Render presentation-ready occurrence items without collapsing parent IDs.
	 *
	 * @param OccurrenceCollectionPage                                       $page       Complete occurrence presentation page.
	 * @param EventListView                                                  $view       List or grid layout.
	 * @param int                                                            $columns    Desktop grid columns.
	 * @param EventCardOptions                                               $options    Card section choices.
	 * @param string                                                         $results_id Stable instance results ID.
	 * @param array<int, \MiMe\WPSimpleEvents\Domain\EventColorPresentation> $colors Canonical event colors.
	 */
	public function render_occurrences(
		OccurrenceCollectionPage $page,
		EventListView $view,
		int $columns,
		EventCardOptions $options,
		string $results_id,
		array $colors = array()
	): string {
		$cards = array();

		foreach ( $page->items as $item ) {
			$card = $this->events->card_presentation(
				$item->presentation,
				$options,
				$item->occurrence->public_key,
				$colors[ $item->occurrence->event_id ] ?? null
			);

			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}

		return $this->collection( $cards, $view, $columns, $results_id );
	}

	/**
	 * Wrap complete card markup in its accessible collection or empty state.
	 *
	 * @param string[]      $cards      Complete escaped card markup.
	 * @param EventListView $view       List or grid layout.
	 * @param int           $columns    Desktop grid columns.
	 * @param string        $results_id Stable instance results ID.
	 */
	private function collection( array $cards, EventListView $view, int $columns, string $results_id ): string {
		if ( array() === $cards ) {
			return sprintf(
				'<div id="%1$s" class="wpse-events-empty" role="status"><p>%2$s</p></div>',
				esc_attr( $results_id ),
				esc_html__( 'No events match your selection.', 'mime-simple-events-calendar' )
			);
		}

		$classes = array(
			'wpse-events-results',
			'wpse-events-view-' . $view->value,
			'wpse-events-columns-' . $columns,
		);

		return sprintf(
			'<div id="%1$s" class="%2$s">%3$s</div>',
			esc_attr( $results_id ),
			esc_attr( implode( ' ', $classes ) ),
			implode( '', $cards )
		);
	}
}
