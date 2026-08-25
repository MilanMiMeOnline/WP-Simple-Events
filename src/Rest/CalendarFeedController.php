<?php
/**
 * Public calendar event feed.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Rest;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Calendar\CalendarEventFormatter;
use MiMe\WPSimpleEvents\Domain\CalendarWindow;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPresenter;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Query\EventRepository;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes only bounded, published, non-password event representations.
 */
final readonly class CalendarFeedController {
	public const REST_NAMESPACE = 'wpse/v1';
	public const REST_ROUTE     = '/events';

	/**
	 * Create the feed controller.
	 *
	 * @param EventRepository               $events    Public event repository.
	 * @param CalendarEventFormatter        $formatter Text-only feed formatter.
	 * @param OccurrenceReadRepository      $occurrences Occurrence-level public repository.
	 * @param OccurrenceCollectionPresenter $occurrence_presenter Shared occurrence presentation bridge.
	 * @param OccurrenceRouteFeature        $occurrence_feature Explicit public recurrence gate.
	 * @param OccurrenceReadiness           $occurrence_readiness Projection readiness gate.
	 */
	public function __construct(
		private EventRepository $events = new EventRepository(),
		private CalendarEventFormatter $formatter = new CalendarEventFormatter(),
		private OccurrenceReadRepository $occurrences = new OccurrenceReadRepository(),
		private OccurrenceCollectionPresenter $occurrence_presenter = new OccurrenceCollectionPresenter(),
		private OccurrenceRouteFeature $occurrence_feature = new OccurrenceRouteFeature(),
		private OccurrenceReadiness $occurrence_readiness = new OccurrenceReadiness()
	) {}

	/**
	 * Register REST discovery after WordPress initializes the API.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the public, read-only calendar collection route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => '__return_true',
				'args'                => $this->collection_parameters(),
			)
		);
	}

	/**
	 * Return one page of events overlapping the requested interval.
	 *
	 * @param WP_REST_Request $request Validated REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$window   = CalendarWindow::from_iso(
				(string) $request->get_param( 'start' ),
				(string) $request->get_param( 'end' )
			);
			$criteria = new EventWindowCriteria(
				$window,
				(int) $request->get_param( 'per_page' ),
				(int) $request->get_param( 'page' ),
				$this->slugs( $request->get_param( 'categories' ) ),
				$this->slugs( $request->get_param( 'tags' ) )
			);
		} catch ( InvalidArgumentException ) {
			return new WP_Error(
				'wpse_invalid_calendar_window',
				__( 'The requested calendar period is invalid or exceeds four hundred days.', 'mime-simple-events-calendar' ),
				array( 'status' => 400 )
			);
		}

		if ( $this->occurrence_feature->enabled() && $this->occurrence_readiness->ready() ) {
			return $this->occurrence_items( $criteria );
		}

		$query = $this->events->query_window( $criteria );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$item = $this->formatter->format( $post );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		$response = new WP_REST_Response( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	/**
	 * Return one exact occurrence page or a non-cacheable unavailable response.
	 *
	 * @param EventWindowCriteria $criteria Validated calendar window criteria.
	 * @return WP_REST_Response|WP_Error
	 */
	private function occurrence_items( EventWindowCriteria $criteria ): WP_REST_Response|WP_Error {
		try {
			$page = $this->occurrence_presenter->present( $this->occurrences->query_window( $criteria ) );
		} catch ( OccurrenceReadException ) {
			$page = null;
		}

		if ( null === $page ) {
			return new WP_Error(
				'wpse_occurrence_calendar_unavailable',
				__( 'The occurrence calendar is temporarily unavailable.', 'mime-simple-events-calendar' ),
				array( 'status' => 503 )
			);
		}

		$items = array();

		foreach ( $page->items as $item ) {
			$formatted = $this->formatter->format_occurrence( $item );

			if ( null === $formatted ) {
				return new WP_Error(
					'wpse_occurrence_calendar_unavailable',
					__( 'The occurrence calendar is temporarily unavailable.', 'mime-simple-events-calendar' ),
					array( 'status' => 503 )
				);
			}

			$items[] = $formatted;
		}

		$response = new WP_REST_Response( $items );
		$response->header( 'X-WP-Total', (string) $page->total );
		$response->header( 'X-WP-TotalPages', (string) $page->total_pages );

		return $response;
	}

	/**
	 * Return the strict public route schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function collection_parameters(): array {
		return array(
			'start'      => array(
				'description'       => __( 'Inclusive local-midnight ISO 8601 calendar start with timezone.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_iso_boundary' ),
			),
			'end'        => array(
				'description'       => __( 'Exclusive local-midnight ISO 8601 calendar end with timezone.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_iso_boundary' ),
			),
			'categories' => array(
				'description'       => __( 'Comma-separated event category slugs.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 2000,
				'validate_callback' => array( $this, 'valid_slug_list' ),
			),
			'tags'       => array(
				'description'       => __( 'Comma-separated event tag slugs.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 2000,
				'validate_callback' => array( $this, 'valid_slug_list' ),
			),
			'page'       => array(
				'description' => __( 'Current result page.', 'mime-simple-events-calendar' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
				'maximum'     => EventWindowCriteria::MAX_PAGE,
			),
			'per_page'   => array(
				'description' => __( 'Maximum events per result page.', 'mime-simple-events-calendar' ),
				'type'        => 'integer',
				'default'     => EventWindowCriteria::MAX_LIMIT,
				'minimum'     => 1,
				'maximum'     => EventWindowCriteria::MAX_LIMIT,
			),
		);
	}

	/**
	 * Validate one standalone ISO boundary before relational validation.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_iso_boundary( mixed $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		try {
			CalendarWindow::local_date_from_iso( $value );
		} catch ( InvalidArgumentException ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate one complete comma-separated term list.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_slug_list( mixed $value ): bool {
		if ( ! is_string( $value ) || strlen( $value ) > 2000 ) {
			return false;
		}

		if ( '' === $value ) {
			return true;
		}

		$values = explode( ',', $value );

		if ( count( $values ) > 20 ) {
			return false;
		}

		foreach ( $values as $item ) {
			$slug = sanitize_title( $item );

			if ( '' === $slug || strlen( $slug ) > 200 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize a comma-separated public term list.
	 *
	 * @param mixed $value Raw REST value.
	 * @return string[]
	 */
	private function slugs( mixed $value ): array {
		$values = explode( ',', is_scalar( $value ) ? (string) $value : '' );
		$slugs  = array();

		foreach ( array_slice( $values, 0, 20 ) as $item ) {
			$slug = sanitize_title( $item );

			if ( '' !== $slug ) {
				$slugs[ $slug ] = $slug;
			}
		}

		return array_values( $slugs );
	}
}
