<?php
/**
 * Public event category legend.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\CalendarLegendVisibility;
use WP_Term;

/** Renders a bounded, text-backed category color legend. */
final readonly class EventCategoryLegend {
	/**
	 * Create the category legend renderer.
	 *
	 * @param EventColorCollection $colors Prepared category colors.
	 */
	public function __construct( private EventColorCollection $colors = new EventColorCollection() ) {}

	/**
	 * Render a legend according to the normalized visibility contract.
	 *
	 * @param CalendarLegendVisibility $visibility               Requested visibility.
	 * @param bool                     $category_filters_visible Whether filters already explain colors.
	 */
	public function render( CalendarLegendVisibility $visibility, bool $category_filters_visible ): string {
		if ( CalendarLegendVisibility::HIDE === $visibility
			|| ( CalendarLegendVisibility::AUTO === $visibility && $category_filters_visible ) ) {
			return '';
		}

		$terms = get_terms(
			array(
				'taxonomy'   => EventTaxonomies::CATEGORY,
				'hide_empty' => true,
				'number'     => 100,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return '';
		}

		$terms  = array_values( $terms );
		$colors = $this->colors->prepare_term_colors( $terms );
		$items  = '';

		foreach ( $terms as $term ) {
			if ( ! isset( $colors[ $term->term_id ] ) ) {
				continue;
			}

			$items .= '<li><span class="wpse-event-category-swatch" aria-hidden="true" style="--wpse-category-color:'
				. esc_attr( $colors[ $term->term_id ] ) . '"></span><span>' . esc_html( $term->name ) . '</span></li>';
		}

		if ( '' === $items ) {
			return '';
		}

		return '<aside class="wpse-calendar-legend" aria-label="'
			. esc_attr__( 'Event category colors', 'mime-simple-events-calendar' ) . '"><p class="wpse-calendar-legend-title">'
			. esc_html__( 'Categories', 'mime-simple-events-calendar' ) . '</p><ul>' . $items . '</ul></aside>';
	}
}
