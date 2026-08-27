<?php
/**
 * Native occurrence-series navigation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteUrlBuilder;
use RuntimeException;

/** Renders bounded series context for the native exact-occurrence fallback. */
final readonly class OccurrenceSeriesNavigationRenderer {
	/**
	 * Create the navigation renderer.
	 *
	 * @param OccurrenceReadRepository  $occurrences Active projection reader.
	 * @param OccurrenceRouteUrlBuilder $urls        Canonical occurrence URL builder.
	 */
	public function __construct(
		private OccurrenceReadRepository $occurrences = new OccurrenceReadRepository(),
		private OccurrenceRouteUrlBuilder $urls = new OccurrenceRouteUrlBuilder()
	) {}

	/**
	 * Render one safe native series-context navigation component.
	 *
	 * @param OccurrencePresentationContext $context Exact occurrence context.
	 */
	public function render( OccurrencePresentationContext $context ): string {
		$series_url = esc_url( $context->series->permalink, array( 'http', 'https' ) );

		if ( '' === $series_url ) {
			return '';
		}

		$previous = null;
		$next     = null;

		try {
			$previous = $this->occurrences->find_previous_public( $context->occurrence );
			$next     = $this->occurrences->find_next_public( $context->occurrence );
		} catch ( InvalidArgumentException | OccurrenceReadException | RuntimeException ) {
			// The canonical series URL is already public. Derived neighbour failure
			// must not expose internal state or remove that useful context.
			$previous = null;
			$next     = null;
		}

		$previous_link = null === $previous ? '' : $this->occurrence_link(
			$context->series->permalink,
			$previous->public_key,
			__( 'Previous date', 'mime-simple-events-calendar' ),
			'wpse-occurrence-navigation-previous'
		);
		$next_link     = null === $next ? '' : $this->occurrence_link(
			$context->series->permalink,
			$next->public_key,
			__( 'Next date', 'mime-simple-events-calendar' ),
			'wpse-occurrence-navigation-next'
		);
		$neighbors     = '' === $previous_link . $next_link
			? ''
			: '<div class="wpse-occurrence-navigation-links">' . $previous_link . $next_link . '</div>';

		return '<nav class="wpse-occurrence-navigation" aria-label="'
			. esc_attr__( 'Other dates for this event', 'mime-simple-events-calendar' )
			. '"><p>'
			. esc_html__( 'This date is part of a repeating event.', 'mime-simple-events-calendar' )
			. ' <a class="wpse-occurrence-series-link" href="' . $series_url . '">'
			. esc_html__( 'View the event series', 'mime-simple-events-calendar' )
			. '</a></p>'
			. $neighbors
			. '</nav>';
	}

	/**
	 * Build one escaped previous or next occurrence link.
	 *
	 * @param string $series_url Canonical public series URL.
	 * @param string $public_key Stable neighbour occurrence key.
	 * @param string $label      Translated link label.
	 * @param string $class_name Internal fixed CSS class.
	 */
	private function occurrence_link(
		string $series_url,
		string $public_key,
		string $label,
		string $class_name
	): string {
		$url = $this->urls->build( $series_url, $public_key );

		return '' === $url
			? ''
			: '<a class="' . esc_attr( $class_name ) . '" href="' . esc_url( $url ) . '">'
				. esc_html( $label )
				. '</a>';
	}
}
