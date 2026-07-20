<?php
/**
 * Public event calendar shortcode.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Calendar\CalendarAssets;
use MiMe\WPSimpleEvents\Calendar\CalendarTimeFormat;
use MiMe\WPSimpleEvents\Domain\CalendarView;
use MiMe\WPSimpleEvents\Domain\EventListView;
use MiMe\WPSimpleEvents\Frontend\EventCardOptions;
use MiMe\WPSimpleEvents\Frontend\EventListRenderer;
use MiMe\WPSimpleEvents\Frontend\RenderInstanceIds;
use MiMe\WPSimpleEvents\Query\EventRepository;
use WP_Post;

/**
 * Renders accessible calendar configuration and a native list fallback.
 */
final class CalendarShortcode implements ShortcodeRenderer {
	/**
	 * Create the shortcode adapter.
	 *
	 * @param EventRepository    $events   Public event repository.
	 * @param EventListRenderer  $renderer Shared no-JavaScript list renderer.
	 * @param CalendarControls   $controls Accessible filter controls.
	 * @param CalendarAssets     $assets   On-demand calendar assets.
	 * @param CalendarTimeFormat $time_format WordPress-to-calendar time presentation.
	 */
	public function __construct(
		private readonly EventRepository $events = new EventRepository(),
		private readonly EventListRenderer $renderer = new EventListRenderer(),
		private readonly CalendarControls $controls = new CalendarControls(),
		private readonly CalendarAssets $assets = new CalendarAssets(),
		private readonly CalendarTimeFormat $time_format = new CalendarTimeFormat()
	) {}

	/**
	 * Register the public calendar shortcode.
	 */
	public function register(): void {
		add_shortcode( 'wpse_calendar', array( $this, 'render' ) );
	}

	/**
	 * Render one isolated progressively enhanced calendar.
	 *
	 * @param array<string, mixed>|string $attributes Raw shortcode attributes.
	 */
	public function render( array|string $attributes = array() ): string {
		$instance    = RenderInstanceIds::next( RenderInstanceIds::CALENDAR );
		$instance_id = 'wpse-calendar-' . $instance;
		$canvas_id   = $instance_id . '-canvas';
		$results_id  = $instance_id . '-fallback-results';
		$prefix      = 'wpse_calendar_' . $instance;
		$request     = $this->request_values();
		$normalized  = CalendarShortcodeAttributes::from_shortcode( is_array( $attributes ) ? $attributes : array() )
			->with_request( $request, $prefix );
		$query       = $this->events->query( $normalized->fallback_criteria( time() ) );
		$posts       = array_values(
			array_filter( $query->posts, static fn ( mixed $post ): bool => $post instanceof WP_Post )
		);
		$config      = wp_json_encode( $this->configuration( $normalized, $prefix ) );

		if ( ! is_string( $config ) ) {
			return '';
		}

		$this->assets->enqueue();

		$output = '<section id="' . esc_attr( $instance_id ) . '" class="wpse-calendar" data-wpse-calendar="'
			. esc_attr( $config ) . '">';

		if ( $normalized->filters ) {
			$output .= $this->controls->render( $normalized, $prefix, $canvas_id, $request );
		}

		$output .= '<p class="wpse-calendar-status" role="status" aria-live="polite" data-wpse-calendar-status></p>';
		$output .= '<div id="' . esc_attr( $canvas_id ) . '" class="wpse-calendar-canvas" aria-label="'
			. esc_attr__( 'Events calendar', 'simple-events-by-mime' ) . '" data-wpse-calendar-canvas hidden></div>';
		$output .= '<p class="wpse-calendar-empty-action" data-wpse-calendar-empty-action hidden><button type="button">'
			. esc_html__( 'Reset filters', 'simple-events-by-mime' ) . '</button></p>';
		$output .= '<div class="wpse-calendar-fallback" aria-labelledby="' . esc_attr( $instance_id . '-fallback-title' ) . '">';
		$output .= '<h3 id="' . esc_attr( $instance_id . '-fallback-title' ) . '">'
			. esc_html__( 'Upcoming events', 'simple-events-by-mime' ) . '</h3>';
		$output .= $this->renderer->render(
			$posts,
			EventListView::LIST,
			1,
			new EventCardOptions( true, true, true ),
			$results_id
		);

		return $output . '</div></section>';
	}

	/**
	 * Build escaped-late JavaScript configuration for one instance.
	 *
	 * @param CalendarShortcodeAttributes $attributes Normalized calendar choices.
	 * @param string                      $prefix     Stable request prefix.
	 * @return array<string, mixed>
	 */
	private function configuration( CalendarShortcodeAttributes $attributes, string $prefix ): array {
		$start_of_week = get_option( 'start_of_week', 1 );
		$start_of_week = is_numeric( $start_of_week ) ? min( 6, max( 0, (int) $start_of_week ) ) : 1;
		$time_format   = get_option( 'time_format', 'H:i' );
		$time_format   = is_string( $time_format ) && '' !== $time_format ? $time_format : 'H:i';

		return array(
			'endpoint'        => rest_url( 'wpse/v1/events' ),
			'initialView'     => $this->fullcalendar_view( $attributes->initial_view ),
			'mobileView'      => $this->fullcalendar_view( $attributes->mobile_view ),
			'locale'          => strtolower( str_replace( '_', '-', determine_locale() ) ),
			'firstDay'        => $start_of_week,
			'eventTimeFormat' => $this->time_format->fullcalendar( $time_format ),
			'perPage'         => 100,
			'maxPages'        => 5,
			'categoryKey'     => $prefix . '_category',
			'tagKey'          => $prefix . '_tag',
			'categories'      => $attributes->category_slugs,
			'tags'            => $attributes->tag_slugs,
			'filtersEnabled'  => $attributes->filters,
			'strings'         => array(
				'previous'   => __( 'Previous', 'simple-events-by-mime' ),
				'next'       => __( 'Next', 'simple-events-by-mime' ),
				'today'      => __( 'Today', 'simple-events-by-mime' ),
				'month'      => __( 'Month', 'simple-events-by-mime' ),
				'list'       => __( 'List', 'simple-events-by-mime' ),
				'loading'    => __( 'Loading events…', 'simple-events-by-mime' ),
				'noEvents'   => __( 'No events match your selection.', 'simple-events-by-mime' ),
				'oneEvent'   => __( '1 event loaded.', 'simple-events-by-mime' ),
				// Translators: %d is the number of loaded events.
				'manyEvents' => __( '%d events loaded.', 'simple-events-by-mime' ),
				'loadError'  => __( 'The calendar could not be loaded. The event list remains available below.', 'simple-events-by-mime' ),
				// Translators: %d is the maximum number of events currently shown.
				'tooMany'    => __( 'Only the first %d events are shown. Narrow the calendar period or filters.', 'simple-events-by-mime' ),
				// Translators: %d is the number of additional events on a calendar day.
				'more'       => __( '%d more', 'simple-events-by-mime' ),
				// Translators: %s is the translated name of the calendar view.
				'viewHint'   => __( '%s view', 'simple-events-by-mime' ),
				'cancelled'  => __( 'Cancelled', 'simple-events-by-mime' ),
				'postponed'  => __( 'Postponed', 'simple-events-by-mime' ),
			),
		);
	}

	/**
	 * Map the public contract to the included FullCalendar plugins.
	 *
	 * @param CalendarView $view Public calendar view.
	 */
	private function fullcalendar_view( CalendarView $view ): string {
		return CalendarView::LIST === $view ? 'listMonth' : 'dayGridMonth';
	}

	/**
	 * Normalize only string-keyed public query parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function request_values(): array {
		$request = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only public filters; all values are allowlisted later.
		foreach ( $_GET as $key => $value ) {
			if ( is_string( $key ) ) {
				$request[ $key ] = wp_unslash( $value );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $request;
	}
}
