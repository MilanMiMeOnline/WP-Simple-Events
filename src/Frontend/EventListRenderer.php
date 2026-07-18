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
	 * @param WP_Post[]        $posts      Public event posts.
	 * @param EventListView    $view       List or grid layout.
	 * @param int              $columns    Desktop grid columns.
	 * @param EventCardOptions $options    Card section choices.
	 * @param string           $results_id Stable instance results ID.
	 */
	public function render(
		array $posts,
		EventListView $view,
		int $columns,
		EventCardOptions $options,
		string $results_id
	): string {
		$cards = array();

		foreach ( $posts as $post ) {
			$card = $this->events->card( $post, $options );

			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}

		if ( array() === $cards ) {
			return sprintf(
				'<div id="%1$s" class="wpse-events-empty" role="status"><p>%2$s</p></div>',
				esc_attr( $results_id ),
				esc_html__( 'No events match your selection.', 'wp-simple-events' )
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
