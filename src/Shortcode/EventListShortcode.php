<?php
/**
 * Public event list shortcode.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Frontend\EventListRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
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
 * Adapts allowlisted shortcode input to shared query and rendering services.
 */
final class EventListShortcode implements ShortcodeRenderer {
	/**
	 * Create the shortcode adapter.
	 *
	 * @param EventRepository               $events   Public event repository.
	 * @param EventListRenderer             $renderer Shared collection renderer.
	 * @param EventListControls             $controls Filter and pagination renderer.
	 * @param FrontendAssets                $assets   Scoped front-end assets.
	 * @param OccurrenceReadRepository      $occurrences Occurrence-level public repository.
	 * @param OccurrenceCollectionPresenter $occurrence_presenter Shared occurrence presentation bridge.
	 * @param OccurrenceRouteFeature        $occurrence_feature Explicit public recurrence gate.
	 * @param OccurrenceReadiness           $occurrence_readiness Projection readiness gate.
	 */
	public function __construct(
		private readonly EventRepository $events = new EventRepository(),
		private readonly EventListRenderer $renderer = new EventListRenderer(),
		private readonly EventListControls $controls = new EventListControls(),
		private readonly FrontendAssets $assets = new FrontendAssets(),
		private readonly OccurrenceReadRepository $occurrences = new OccurrenceReadRepository(),
		private readonly OccurrenceCollectionPresenter $occurrence_presenter = new OccurrenceCollectionPresenter(),
		private readonly OccurrenceRouteFeature $occurrence_feature = new OccurrenceRouteFeature(),
		private readonly OccurrenceReadiness $occurrence_readiness = new OccurrenceReadiness()
	) {}

	/**
	 * Register the public shortcode.
	 */
	public function register(): void {
		add_shortcode( 'wpse_events', array( $this, 'render' ) );
	}

	/**
	 * Return one isolated event list or grid.
	 *
	 * @param array<string, mixed>|string $attributes Raw shortcode attributes.
	 */
	public function render( array|string $attributes = array() ): string {
		$instance    = RenderInstanceIds::next( RenderInstanceIds::EVENT_LIST );
		$instance_id = 'wpse-events-' . $instance;
		$results_id  = $instance_id . '-results';
		$prefix      = 'wpse_' . $instance;
		$request     = $this->request_values();
		$configured  = EventListAttributes::from_shortcode( is_array( $attributes ) ? $attributes : array() );
		$normalized  = $configured->with_request( $request, $prefix );
		$criteria    = $normalized->criteria( time() );
		$occurrences = $this->occurrence_feature->enabled() && $this->occurrence_readiness->ready()
			? $this->occurrence_page( $criteria )
			: null;
		$query       = null === $occurrences ? $this->events->query( $criteria ) : null;
		$posts       = null === $query
			? array()
			: array_values(
				array_filter( $query->posts, static fn ( mixed $post ): bool => $post instanceof WP_Post )
			);

		$this->assets->enqueue_filters();

		$output = '<div id="' . esc_attr( $instance_id ) . '" class="wpse-events">';

		if ( $normalized->filters ) {
			$output .= $this->controls->filters( $normalized, $prefix, $results_id, $request, $configured );

			if ( $normalized->filter_presentation->show_results ) {
				$total   = null !== $occurrences ? $occurrences->total : (int) $query->found_posts;
				$message = 1 === $total
					? __( '1 event found.', 'mime-simple-events-calendar' )
					: sprintf(
						/* Translators: %d is the number of matching events. */
						__( '%d events found.', 'mime-simple-events-calendar' ),
						$total
					);
				$output .= '<p class="wpse-events-filter-status" role="status" aria-live="polite">' . esc_html( $message ) . '</p>';
			}
		}

		$output .= null !== $occurrences
			? $this->renderer->render_occurrences(
				$occurrences,
				$normalized->view,
				$normalized->columns,
				$normalized->card_options(),
				$results_id
			)
			: $this->renderer->render(
				$posts,
				$normalized->view,
				$normalized->columns,
				$normalized->card_options(),
				$results_id
			);

		if ( $normalized->pagination ) {
			$output .= $this->controls->pagination(
				$normalized->page,
				null !== $occurrences ? $occurrences->total_pages : (int) $query->max_num_pages,
				$prefix . '_page'
			);
		}

		return $output . '</div>';
	}

	/**
	 * Return one complete occurrence page, using an empty unavailable state on failure.
	 *
	 * @param EventQueryCriteria $criteria Validated list criteria.
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
