<?php
/**
 * Exact public occurrence REST resource.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Rest;

use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationResolver;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes one read-only occurrence after the shared public eligibility boundary.
 */
final readonly class OccurrenceRestController {
	public const REST_NAMESPACE = 'wpse/v2';
	public const REST_ROUTE     = '/events/(?P<event_id>[1-9][0-9]*)/occurrences/(?P<occurrence>[a-f0-9]{32})';

	/**
	 * Create the controller.
	 *
	 * @param OccurrencePresentationProvider $occurrences Exact public occurrence provider.
	 * @param OccurrenceRouteController      $routes      Shared canonical URL builder.
	 * @param OccurrenceRestSerializer       $serializer  Bounded public serializer.
	 */
	public function __construct(
		private OccurrencePresentationProvider $occurrences = new OccurrencePresentationResolver(),
		private OccurrenceRouteController $routes = new OccurrenceRouteController(),
		private OccurrenceRestSerializer $serializer = new OccurrenceRestSerializer()
	) {}

	/** Register the REST route only when this gated service is composed. */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register one strict public read route. */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'event_id'   => array(
						'description'       => __( 'Canonical public event post ID.', 'mime-simple-events-calendar' ),
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'validate_callback' => array( $this, 'valid_event_id' ),
					),
					'occurrence' => array(
						'description'       => __( 'Stable lowercase public occurrence key.', 'mime-simple-events-calendar' ),
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 32,
						'maxLength'         => 32,
						'validate_callback' => array( $this, 'valid_occurrence_key' ),
					),
				),
			)
		);
	}

	/**
	 * Return one exact public occurrence or an indistinguishable not-found error.
	 *
	 * @param WP_REST_Request $request Validated public request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$event_id   = (int) $request->get_param( 'event_id' );
		$public_key = (string) $request->get_param( 'occurrence' );
		$context    = $this->occurrences->resolve_public( $event_id, $public_key );

		if ( null === $context
			|| $context->series->event->ID !== $event_id
			|| $context->occurrence->event_id !== $event_id
			|| ! hash_equals( $context->occurrence->public_key, $public_key )
		) {
			return $this->not_found();
		}

		$data = $this->serializer->serialize( $context, $this->routes->canonical_url( $context ) );

		return null === $data
			? $this->not_found()
			: new WP_REST_Response( $data, 200 );
	}

	/**
	 * Validate a positive exact integer without accepting partial numerics.
	 *
	 * @param mixed $value Raw route value.
	 */
	public function valid_event_id( mixed $value ): bool {
		if ( is_int( $value ) ) {
			return $value > 0;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return false;
		}

		return false !== filter_var( $value, FILTER_VALIDATE_INT );
	}

	/**
	 * Validate one exact lowercase public occurrence key.
	 *
	 * @param mixed $value Raw route value.
	 */
	public function valid_occurrence_key( mixed $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $value );
	}

	/** Return one privacy-preserving absence response for every ineligible target. */
	private function not_found(): WP_Error {
		return new WP_Error(
			'wpse_occurrence_not_found',
			__( 'The requested event occurrence was not found.', 'mime-simple-events-calendar' ),
			array( 'status' => 404 )
		);
	}
}
