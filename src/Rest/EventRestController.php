<?php
/**
 * Event REST write validation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Rest;

use MiMe\WPSimpleEvents\Application\EventInputMapper;
use MiMe\WPSimpleEvents\Application\EventPersistence;
use MiMe\WPSimpleEvents\Application\EventPublicationPolicy;
use MiMe\WPSimpleEvents\Application\EventValidationMessages;
use MiMe\WPSimpleEvents\Application\EventValidator;
use MiMe\WPSimpleEvents\Application\ValidatedEventData;
use MiMe\WPSimpleEvents\Content\EventPostType;
use stdClass;
use WP_Error;
use WP_Post;
use WP_REST_Request;

/**
 * Applies the same validation and derived metadata rules to REST writes.
 */
final class EventRestController {
	/**
	 * Validated values keyed by the current request object.
	 *
	 * @var array<int, ValidatedEventData>
	 */
	private array $validated_requests = array();

	/**
	 * Create the REST save controller.
	 *
	 * @param EventInputMapper        $mapper      REST input mapper.
	 * @param EventValidator          $validator   Central validator.
	 * @param EventPersistence        $persistence Validated persistence gateway.
	 * @param EventPublicationPolicy  $policy      Publication completeness policy.
	 * @param EventValidationMessages $messages    Translated validation messages.
	 */
	public function __construct(
		private readonly EventInputMapper $mapper = new EventInputMapper(),
		private readonly EventValidator $validator = new EventValidator(),
		private readonly EventPersistence $persistence = new EventPersistence(),
		private readonly EventPublicationPolicy $policy = new EventPublicationPolicy(),
		private readonly EventValidationMessages $messages = new EventValidationMessages()
	) {}

	/**
	 * Register REST write hooks.
	 */
	public function register(): void {
		add_filter( 'rest_pre_insert_' . EventPostType::POST_TYPE, array( $this, 'validate' ), 10, 2 );
		add_action( 'rest_after_insert_' . EventPostType::POST_TYPE, array( $this, 'persist' ), 10, 3 );
	}

	/**
	 * Validate one prepared REST event.
	 *
	 * Reject invalid REST writes before WordPress creates or updates the post.
	 *
	 * @param stdClass|WP_Error $prepared_post Prepared post or an earlier error.
	 * @param WP_REST_Request   $request       REST request.
	 * @return stdClass|WP_Error
	 */
	public function validate( stdClass|WP_Error $prepared_post, WP_REST_Request $request ): stdClass|WP_Error {
		if ( $prepared_post instanceof WP_Error ) {
			return $prepared_post;
		}

		$raw_meta = $request->get_param( 'meta' );
		$meta     = array();

		if ( is_array( $raw_meta ) ) {
			foreach ( $raw_meta as $key => $value ) {
				if ( is_string( $key ) ) {
					$meta[ $key ] = $value;
				}
			}
		}

		$post_id     = (int) $request->get_param( 'id' );
		$post_status = isset( $prepared_post->post_status ) && is_string( $prepared_post->post_status )
			? $prepared_post->post_status
			: $this->existing_post_status( $post_id );
		$result      = $this->validator->validate(
			$this->mapper->from_rest( $meta, $post_id ),
			$this->policy->requires_date_range( $post_status )
		);

		if ( ! $result->is_valid() ) {
			$error_messages = array();

			foreach ( $result->errors() as $error ) {
				$error_messages[ $error->value ] = $this->messages->message( $error );
			}

			$primary_message = reset( $error_messages );

			if ( ! is_string( $primary_message ) ) {
				$primary_message = __( 'The event could not be saved because its details are invalid.', 'wp-simple-events' );
			}

			return new WP_Error(
				'wpse_invalid_event',
				$primary_message,
				array(
					'status'      => 400,
					'wpse_errors' => $error_messages,
				)
			);
		}

		$data = $result->data();

		if ( null !== $data ) {
			$this->validated_requests[ spl_object_id( $request ) ] = $data;
		}

		return $prepared_post;
	}

	/**
	 * Persist canonical and derived fields after core REST meta processing.
	 *
	 * @param WP_Post         $post     Saved event post.
	 * @param WP_REST_Request $request  REST request.
	 * @param bool            $creating Whether this created a new event.
	 */
	public function persist( WP_Post $post, WP_REST_Request $request, bool $creating ): void {
		unset( $creating );

		$request_id = spl_object_id( $request );
		$data       = $this->validated_requests[ $request_id ] ?? null;

		if ( null === $data ) {
			return;
		}

		$this->persistence->persist( $post->ID, $data );
		unset( $this->validated_requests[ $request_id ] );
	}

	/**
	 * Return the current status for a REST update or the new-event default.
	 *
	 * @param int $post_id Existing event ID, or zero.
	 */
	private function existing_post_status( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return 'draft';
		}

		$status = get_post_status( $post_id );

		return is_string( $status ) ? $status : 'draft';
	}
}
