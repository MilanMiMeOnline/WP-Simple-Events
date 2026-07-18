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
use MiMe\WPSimpleEvents\Query\EventRepository;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;
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
	 * @param EventRepository        $events    Public event repository.
	 * @param CalendarEventFormatter $formatter Text-only feed formatter.
	 */
	public function __construct(
		private EventRepository $events = new EventRepository(),
		private CalendarEventFormatter $formatter = new CalendarEventFormatter()
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
				__( 'The requested calendar period is invalid or exceeds four hundred days.', 'wp-simple-events' ),
				array( 'status' => 400 )
			);
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
	 * Return the strict public route schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function collection_parameters(): array {
		return array(
			'start'      => array(
				'description'       => __( 'Inclusive ISO 8601 calendar start with timezone.', 'wp-simple-events' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_iso_boundary' ),
			),
			'end'        => array(
				'description'       => __( 'Exclusive ISO 8601 calendar end with timezone.', 'wp-simple-events' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_iso_boundary' ),
			),
			'categories' => array(
				'description'       => __( 'Comma-separated event category slugs.', 'wp-simple-events' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 2000,
				'validate_callback' => array( $this, 'valid_slug_list' ),
			),
			'tags'       => array(
				'description'       => __( 'Comma-separated event tag slugs.', 'wp-simple-events' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 2000,
				'validate_callback' => array( $this, 'valid_slug_list' ),
			),
			'page'       => array(
				'description' => __( 'Current result page.', 'wp-simple-events' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
				'maximum'     => EventWindowCriteria::MAX_PAGE,
			),
			'per_page'   => array(
				'description' => __( 'Maximum events per result page.', 'wp-simple-events' ),
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
			CalendarWindow::timestamp_from_iso( $value );
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
