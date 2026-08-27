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
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPage;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPresenter;
use MiMe\WPSimpleEvents\Frontend\RenderInstanceIds;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Query\EventRepository;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use WP_Post;

/**
 * Renders accessible calendar configuration and a native list fallback.
 */
final class CalendarShortcode implements ShortcodeRenderer {
	/**
	 * Create the shortcode adapter.
	 *
	 * @param EventRepository               $events   Public event repository.
	 * @param EventListRenderer             $renderer Shared no-JavaScript list renderer.
	 * @param CalendarControls              $controls Accessible filter controls.
	 * @param CalendarAssets                $assets   On-demand calendar assets.
	 * @param CalendarTimeFormat            $time_format WordPress-to-calendar time presentation.
	 * @param OccurrenceReadRepository      $occurrences Occurrence-level public repository.
	 * @param OccurrenceCollectionPresenter $occurrence_presenter Shared occurrence presentation bridge.
	 * @param OccurrenceRouteFeature        $occurrence_feature Explicit public recurrence gate.
	 * @param OccurrenceReadiness           $occurrence_readiness Projection readiness gate.
	 */
	public function __construct(
		private readonly EventRepository $events = new EventRepository(),
		private readonly EventListRenderer $renderer = new EventListRenderer(),
		private readonly CalendarControls $controls = new CalendarControls(),
		private readonly CalendarAssets $assets = new CalendarAssets(),
		private readonly CalendarTimeFormat $time_format = new CalendarTimeFormat(),
		private readonly OccurrenceReadRepository $occurrences = new OccurrenceReadRepository(),
		private readonly OccurrenceCollectionPresenter $occurrence_presenter = new OccurrenceCollectionPresenter(),
		private readonly OccurrenceRouteFeature $occurrence_feature = new OccurrenceRouteFeature(),
		private readonly OccurrenceReadiness $occurrence_readiness = new OccurrenceReadiness()
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
		$configured  = CalendarShortcodeAttributes::from_shortcode( is_array( $attributes ) ? $attributes : array() );
		$normalized  = $configured->with_request( $request, $prefix );
		$criteria    = $normalized->fallback_criteria( time() );
		$occurrences = $this->occurrence_feature->enabled() && $this->occurrence_readiness->ready()
			? $this->occurrence_page( $criteria )
			: null;
		$query       = null === $occurrences ? $this->events->query( $criteria ) : null;
		$posts       = null === $query
			? array()
			: array_values(
				array_filter( $query->posts, static fn ( mixed $post ): bool => $post instanceof WP_Post )
			);
		$config      = wp_json_encode( $this->configuration( $normalized, $configured, $prefix ) );

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
			. esc_attr__( 'Events calendar', 'mime-simple-events-calendar' ) . '" data-wpse-calendar-canvas hidden></div>';
		$output .= '<p class="wpse-calendar-empty-action" data-wpse-calendar-empty-action hidden><button type="button">'
			. esc_html__( 'Reset filters', 'mime-simple-events-calendar' ) . '</button></p>';
		$output .= '<div class="wpse-calendar-fallback" aria-labelledby="' . esc_attr( $instance_id . '-fallback-title' ) . '">';
		$output .= '<' . esc_attr( $normalized->fallback_heading_level ) . ' id="'
			. esc_attr( $instance_id . '-fallback-title' ) . '">'
			. esc_html__( 'Upcoming events', 'mime-simple-events-calendar' )
			. '</' . esc_attr( $normalized->fallback_heading_level ) . '>';
		$options = new EventCardOptions( true, true, true, true, true, 30, $normalized->fallback_heading_level );
		$output .= null !== $occurrences
			? $this->renderer->render_occurrences(
				$occurrences,
				EventListView::LIST,
				1,
				$options,
				$results_id
			)
			: $this->renderer->render(
				$posts,
				EventListView::LIST,
				1,
				$options,
				$results_id
			);

		return $output . '</div></section>';
	}

	/**
	 * Return one complete fallback occurrence page or an empty unavailable state.
	 *
	 * @param EventQueryCriteria $criteria Validated fallback list criteria.
	 */
	private function occurrence_page( EventQueryCriteria $criteria ): OccurrenceCollectionPage {
		try {
			$page = $this->occurrence_presenter->present( $this->occurrences->query( $criteria ) );

			return $page ?? new OccurrenceCollectionPage( array(), 0, 0 );
		} catch ( OccurrenceReadException ) {
			return new OccurrenceCollectionPage( array(), 0, 0 );
		}
	}

	/**
	 * Build escaped-late JavaScript configuration for one instance.
	 *
	 * @param CalendarShortcodeAttributes $attributes Current normalized calendar choices.
	 * @param CalendarShortcodeAttributes $configured Original per-instance choices.
	 * @param string                      $prefix     Stable request prefix.
	 * @return array<string, mixed>
	 */
	private function configuration(
		CalendarShortcodeAttributes $attributes,
		CalendarShortcodeAttributes $configured,
		string $prefix
	): array {
		$start_of_week = get_option( 'start_of_week', 1 );
		$start_of_week = is_numeric( $start_of_week ) ? min( 6, max( 0, (int) $start_of_week ) ) : 1;
		$time_format   = get_option( 'time_format', 'H:i' );
		$time_format   = is_string( $time_format ) && '' !== $time_format ? $time_format : 'H:i';

		return array(
			// Keep browser requests on the page's active origin. This remains valid
			// for subdirectory installs while avoiding host-alias CORS failures in
			// proxies, local environments and domain-transition windows.
			'endpoint'          => wp_make_link_relative( rest_url( 'wpse/v1/events' ) ),
			'initialView'       => $this->fullcalendar_view( $attributes->initial_view ),
			'mobileView'        => $this->fullcalendar_view( $attributes->mobile_view ),
			'initialDate'       => $attributes->initial_date,
			'locale'            => strtolower( str_replace( '_', '-', determine_locale() ) ),
			'firstDay'          => $start_of_week,
			'eventTimeFormat'   => $this->time_format->fullcalendar( $time_format ),
			'perPage'           => 100,
			'maxPages'          => 5,
			'categoryKey'       => $prefix . '_category',
			'tagKey'            => $prefix . '_tag',
			'applyKey'          => $prefix . '_apply',
			'categories'        => $attributes->category_slugs,
			'tags'              => $attributes->tag_slugs,
			'initialCategories' => $configured->category_slugs,
			'initialTags'       => $configured->tag_slugs,
			'filtersEnabled'    => $attributes->filters,
			'showNavigation'    => $attributes->show_navigation,
			'showToday'         => $attributes->show_today,
			'showViewSwitcher'  => $attributes->show_view_switcher,
			'strings'           => array(
				'previous'   => __( 'Previous', 'mime-simple-events-calendar' ),
				'next'       => __( 'Next', 'mime-simple-events-calendar' ),
				'today'      => __( 'Today', 'mime-simple-events-calendar' ),
				'month'      => __( 'Month', 'mime-simple-events-calendar' ),
				'list'       => __( 'List', 'mime-simple-events-calendar' ),
				'loading'    => __( 'Loading events…', 'mime-simple-events-calendar' ),
				'noEvents'   => __( 'No events match your selection.', 'mime-simple-events-calendar' ),
				'oneEvent'   => __( '1 event loaded.', 'mime-simple-events-calendar' ),
				// Translators: %d is the number of loaded events.
				'manyEvents' => __( '%d events loaded.', 'mime-simple-events-calendar' ),
				'loadError'  => __( 'The calendar could not be loaded. The event list remains available below.', 'mime-simple-events-calendar' ),
				// Translators: %d is the maximum number of events currently shown.
				'tooMany'    => __( 'Only the first %d events are shown. Narrow the calendar period or filters.', 'mime-simple-events-calendar' ),
				// Translators: %d is the number of additional events on a calendar day.
				'more'       => __( '%d more', 'mime-simple-events-calendar' ),
				// Translators: %s is the translated name of the calendar view.
				'viewHint'   => __( '%s view', 'mime-simple-events-calendar' ),
				'cancelled'  => __( 'Cancelled', 'mime-simple-events-calendar' ),
				'postponed'  => __( 'Postponed', 'mime-simple-events-calendar' ),
				// Translators: %s is the displayed start time.
				'startsAt'   => __( 'Starts at %s', 'mime-simple-events-calendar' ),
				'continues'  => __( 'Continues', 'mime-simple-events-calendar' ),
				// Translators: %s is the displayed end time.
				'endsAt'     => __( 'Ends at %s', 'mime-simple-events-calendar' ),
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
