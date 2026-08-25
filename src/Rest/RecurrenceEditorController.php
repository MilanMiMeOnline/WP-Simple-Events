<?php
/**
 * Authenticated recurrence editor REST routes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Rest;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorError;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorException;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorService;
use MiMe\WPSimpleEvents\Application\RecurrencePersistenceError;
use MiMe\WPSimpleEvents\Application\RecurrencePersistenceResult;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateCodec;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEditScope;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingReplacementCodec;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Adapts exact REST shapes into the capability-checked editor application service.
 */
final readonly class RecurrenceEditorController {
	public const REST_NAMESPACE = 'wpse/v1';
	public const REST_ROUTE     = '/events/(?P<id>[\d]+)/recurrence';

	/**
	 * Create the recurrence editor controller.
	 *
	 * @param RecurrenceEditorService             $editor     Authorized editor workflow.
	 * @param RecurrenceAggregateCodec            $codec      Exact untrusted aggregate parser.
	 * @param RecurrenceAggregateRevision         $revisions Revision format validator.
	 * @param RecurrenceEditorSerializer          $serializer Bounded response serializer.
	 * @param RecurrenceFollowingReplacementCodec $following_codec Strict replacement parser.
	 */
	public function __construct(
		private RecurrenceEditorService $editor = new RecurrenceEditorService(),
		private RecurrenceAggregateCodec $codec = new RecurrenceAggregateCodec(),
		private RecurrenceAggregateRevision $revisions = new RecurrenceAggregateRevision(),
		private RecurrenceEditorSerializer $serializer = new RecurrenceEditorSerializer(),
		private RecurrenceFollowingReplacementCodec $following_codec = new RecurrenceFollowingReplacementCodec()
	) {}

	/**
	 * Register authenticated editor routes after REST initialization.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register context, preview and confirmed-save routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_context' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => $this->context_parameters(),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $this->mutation_parameters( false ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/following/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'following_preview' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $this->following_parameters(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $this->mutation_parameters( true ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/occurrences',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_occurrences' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $this->window_parameters(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/occurrence',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_occurrence_context' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $this->occurrence_parameters(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/disable/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disable_preview' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $this->disable_parameters( false ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/disable/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disable_save' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => $this->disable_parameters( true ),
			)
		);
	}

	/**
	 * Authorize every route against the mapped event edit capability.
	 *
	 * @param WP_REST_Request $request Current REST request.
	 */
	public function can_edit( WP_REST_Request $request ): bool {
		$event_id = (int) $request->get_param( 'id' );

		return $event_id > 0
			&& EventPostType::POST_TYPE === get_post_type( $event_id )
			&& current_user_can( 'edit_post', $event_id );
	}

	/**
	 * Return canonical or bootstrapped editor state.
	 *
	 * @param WP_REST_Request $request Validated authorized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_context( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$context = $this->editor->context( (int) $request->get_param( 'id' ) );
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		return new WP_REST_Response( $this->serializer->context( $context ) );
	}

	/**
	 * Return one scope-safe bounded impact preview and confirmation.
	 *
	 * @param WP_REST_Request $request Validated authorized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$input   = $this->mutation_input( $request );
			$preview = $this->editor->preview(
				(int) $request->get_param( 'id' ),
				$input['aggregate'],
				$input['window'],
				$input['scope'],
				$input['target'],
				$input['revision']
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_request();
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		return new WP_REST_Response( $this->serializer->preview( $preview ) );
	}

	/**
	 * Build one scope-safe future proposal entirely on the server.
	 *
	 * @param WP_REST_Request $request Validated authorized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function following_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$preview = $this->editor->following_preview(
				(int) $request->get_param( 'id' ),
				(string) $request->get_param( 'target' ),
				$this->following_codec->decode( $request->get_param( 'replacement' ) ),
				$this->window_input( $request ),
				(string) $request->get_param( 'revision' )
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_request();
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		return new WP_REST_Response( $this->serializer->following_preview( $preview ) );
	}

	/**
	 * Revalidate and save one exactly confirmed preview.
	 *
	 * @param WP_REST_Request $request Validated authorized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$input  = $this->mutation_input( $request );
			$result = $this->editor->save(
				(int) $request->get_param( 'id' ),
				$input['aggregate'],
				$input['window'],
				$input['scope'],
				$input['target'],
				$input['revision'],
				$request->get_param( 'confirmation' )
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_request();
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		if ( ! $result->successful() ) {
			return $this->persistence_error( $result );
		}

		try {
			$context = $this->editor->context( (int) $request->get_param( 'id' ) );
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		return new WP_REST_Response(
			array(
				'changed' => $result->changed(),
				'context' => $this->serializer->context( $context ),
			)
		);
	}

	/**
	 * Return bounded effective occurrences that may survive a conversion.
	 *
	 * @param WP_REST_Request $request Validated authorized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_occurrences( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$occurrences = $this->editor->occurrences(
				(int) $request->get_param( 'id' ),
				$this->window_input( $request )
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_request();
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		return new WP_REST_Response( array( 'occurrences' => $this->serializer->occurrences( $occurrences ) ) );
	}

	/**
	 * Return server-resolved edit state for one selected occurrence.
	 *
	 * @param WP_REST_Request $request Validated authorized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_occurrence_context( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$context = $this->editor->occurrence_context(
				(int) $request->get_param( 'id' ),
				(string) $request->get_param( 'target' ),
				$this->window_input( $request )
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_request();
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		return new WP_REST_Response( $this->serializer->occurrence_context( $context ) );
	}

	/**
	 * Return a destructive preview for keeping one selected occurrence.
	 *
	 * @param WP_REST_Request $request Validated authorized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function disable_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$preview = $this->editor->disable_preview(
				(int) $request->get_param( 'id' ),
				(string) $request->get_param( 'target' ),
				$this->window_input( $request ),
				(string) $request->get_param( 'revision' )
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_request();
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		return new WP_REST_Response( $this->serializer->disable_preview( $preview ) );
	}

	/**
	 * Confirm and convert one recurring series into a one-off event.
	 *
	 * @param WP_REST_Request $request Validated authorized REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function disable_save( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$result = $this->editor->disable_save(
				(int) $request->get_param( 'id' ),
				(string) $request->get_param( 'target' ),
				$this->window_input( $request ),
				(string) $request->get_param( 'revision' ),
				$request->get_param( 'confirmation' )
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_request();
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		if ( ! $result->successful() ) {
			return $this->persistence_error( $result );
		}

		try {
			$context = $this->editor->context( (int) $request->get_param( 'id' ) );
		} catch ( RecurrenceEditorException $exception ) {
			return $this->editor_error( $exception );
		}

		return new WP_REST_Response(
			array(
				'changed' => $result->changed(),
				'context' => $this->serializer->context( $context ),
			)
		);
	}

	/**
	 * Decode one exact mutation request after REST scalar validation.
	 *
	 * @param WP_REST_Request $request Validated authorized request.
	 * @return array{aggregate: RecurrenceAggregate, window: RecurrenceGenerationWindow, scope: RecurrenceEditScope, target: string|null, revision: string}
	 * @throws InvalidArgumentException When relational or aggregate input is invalid.
	 */
	private function mutation_input( WP_REST_Request $request ): array {
		$aggregate = $this->codec->decode( $request->get_param( 'aggregate' ) );
		$scope     = RecurrenceEditScope::tryFrom( (string) $request->get_param( 'scope' ) );
		$target    = $request->get_param( 'target' );
		$revision  = $request->get_param( 'revision' );

		if ( null === $scope || ! is_string( $target ) || ! is_string( $revision ) || ! $this->revisions->valid( $revision ) ) {
			throw new InvalidArgumentException( 'The recurrence editor request is invalid.' );
		}

		return array(
			'aggregate' => $aggregate,
			'window'    => $this->window_input( $request ),
			'scope'     => $scope,
			'target'    => '' === $target ? null : $target,
			'revision'  => $revision,
		);
	}

	/**
	 * Decode one exact bounded window shared by all editor routes.
	 *
	 * @param WP_REST_Request $request Validated authorized request.
	 */
	private function window_input( WP_REST_Request $request ): RecurrenceGenerationWindow {
		return RecurrenceGenerationWindow::between(
			(string) $request->get_param( 'from_date' ),
			(string) $request->get_param( 'through_date' ),
			(int) $request->get_param( 'max_rows' )
		);
	}

	/**
	 * Return exact context route parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function context_parameters(): array {
		return array(
			'id' => array(
				'description' => __( 'Canonical event post ID.', 'mime-simple-events-calendar' ),
				'type'        => 'integer',
				'required'    => true,
				'minimum'     => 1,
			),
		);
	}

	/**
	 * Return strict mutation parameter schemas.
	 *
	 * The complete nested aggregate is additionally parsed through the exact codec;
	 * no partial object, unknown key or weak scalar coercion survives that boundary.
	 *
	 * @param bool $saving Whether signed confirmation is required.
	 * @return array<string, array<string, mixed>>
	 */
	private function mutation_parameters( bool $saving ): array {
		$parameters = $this->window_parameters() + array(
			'aggregate' => array(
				'description'       => __( 'Complete versioned recurrence aggregate.', 'mime-simple-events-calendar' ),
				'type'              => 'object',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_aggregate' ),
			),
			'scope'     => array(
				'description' => __( 'Explicit recurrence edit scope.', 'mime-simple-events-calendar' ),
				'type'        => 'string',
				'required'    => true,
				'enum'        => array_column( RecurrenceEditScope::cases(), 'value' ),
			),
			'target'    => array(
				'description'       => __( 'Selected recurrence identity, or an empty string for complete-series edits.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'maxLength'         => 64,
				'validate_callback' => array( $this, 'valid_target' ),
			),
			'revision'  => array(
				'description'       => __( 'Exact canonical recurrence revision used by the editor.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_revision' ),
			),
		);

		if ( $saving ) {
			$parameters['confirmation'] = array(
				'description' => __( 'Server-signed confirmation for this exact preview.', 'mime-simple-events-calendar' ),
				'type'        => 'string',
				'required'    => true,
				'pattern'     => '^[a-f0-9]{64}$',
			);
		}

		return $parameters;
	}

	/**
	 * Return strict shared generation-window parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function window_parameters(): array {
		return $this->context_parameters() + array(
			'from_date'    => array(
				'description'       => __( 'Inclusive canonical preview start date.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_date' ),
			),
			'through_date' => array(
				'description'       => __( 'Inclusive canonical preview end date.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_date' ),
			),
			'max_rows'     => array(
				'description' => __( 'Maximum projected preview rows.', 'mime-simple-events-calendar' ),
				'type'        => 'integer',
				'required'    => true,
				'minimum'     => 1,
				'maximum'     => RecurrenceGenerationWindow::MAX_ROWS,
			),
		);
	}

	/**
	 * Return strict occurrence edit-context parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function occurrence_parameters(): array {
		return $this->window_parameters() + array(
			'target' => array(
				'description'       => __( 'Exact recurrence identity selected for occurrence editing.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'maxLength'         => 64,
				'validate_callback' => array( $this, 'valid_required_target' ),
			),
		);
	}

	/**
	 * Return strict this-and-following preview parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function following_parameters(): array {
		return $this->window_parameters() + array(
			'target'      => array(
				'description'       => __( 'Generated occurrence identity where the replacement schedule begins.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'maxLength'         => 64,
				'validate_callback' => array( $this, 'valid_generated_target' ),
			),
			'revision'    => array(
				'description'       => __( 'Exact canonical recurrence revision used by the editor.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_revision' ),
			),
			'replacement' => array(
				'description'       => __( 'Replacement date template and recurrence definition.', 'mime-simple-events-calendar' ),
				'type'              => 'object',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_following_replacement' ),
			),
		);
	}

	/**
	 * Return strict recurrence-disable parameters.
	 *
	 * @param bool $saving Whether signed confirmation is required.
	 * @return array<string, array<string, mixed>>
	 */
	private function disable_parameters( bool $saving ): array {
		$parameters = $this->window_parameters() + array(
			'target'   => array(
				'description'       => __( 'Exact recurrence identity retained as a one-off event.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'maxLength'         => 64,
				'validate_callback' => array( $this, 'valid_required_target' ),
			),
			'revision' => array(
				'description'       => __( 'Exact canonical recurrence revision used by the editor.', 'mime-simple-events-calendar' ),
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'valid_revision' ),
			),
		);

		if ( $saving ) {
			$parameters['confirmation'] = array(
				'description' => __( 'Server-signed confirmation for this exact destructive preview.', 'mime-simple-events-calendar' ),
				'type'        => 'string',
				'required'    => true,
				'pattern'     => '^[a-f0-9]{64}$',
			);
		}

		return $parameters;
	}

	/**
	 * Validate one complete nested recurrence aggregate.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_aggregate( mixed $value ): bool {
		try {
			$this->codec->decode( $value );
		} catch ( InvalidArgumentException ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate one optional canonical recurrence identity.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_target( mixed $value ): bool {
		return is_string( $value )
			&& ( '' === $value || ( 'one-off' !== $value && OccurrenceIdentity::valid_recurrence_id( $value ) ) );
	}

	/**
	 * Validate one required non-one-off recurrence identity.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_required_target( mixed $value ): bool {
		return is_string( $value )
			&& '' !== $value
			&& 'one-off' !== $value
			&& OccurrenceIdentity::valid_recurrence_id( $value );
	}

	/**
	 * Validate one required generated recurrence identity.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_generated_target( mixed $value ): bool {
		return is_string( $value ) && OccurrenceIdentity::is_generated_recurrence_id( $value );
	}

	/**
	 * Validate one exact following replacement object.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_following_replacement( mixed $value ): bool {
		try {
			$this->following_codec->decode( $value );
		} catch ( InvalidArgumentException ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate one exact revision token.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_revision( mixed $value ): bool {
		return $this->revisions->valid( $value );
	}

	/**
	 * Validate one standalone canonical date.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_date( mixed $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		try {
			RecurrenceGenerationWindow::between( $value, $value, 1 );
		} catch ( InvalidArgumentException ) {
			return false;
		}

		return true;
	}

	/**
	 * Return one generic malformed-request response.
	 */
	private function invalid_request(): WP_Error {
		return new WP_Error(
			'wpse_invalid_recurrence_request',
			__( 'The recurrence request is incomplete or invalid.', 'mime-simple-events-calendar' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Map one allowlisted editor error to a non-sensitive REST response.
	 *
	 * @param RecurrenceEditorException $exception Stable editor failure.
	 */
	private function editor_error( RecurrenceEditorException $exception ): WP_Error {
		$status = match ( $exception->error ) {
			RecurrenceEditorError::INVALID_EVENT        => 404,
			RecurrenceEditorError::FORBIDDEN            => 403,
			RecurrenceEditorError::STALE_REVISION       => 409,
			RecurrenceEditorError::INVALID_STATE        => 409,
			RecurrenceEditorError::INVALID_PROPOSAL     => 400,
			RecurrenceEditorError::INVALID_CONFIRMATION => 400,
		};

		return new WP_Error(
			'wpse_recurrence_' . $exception->error->value,
			__( 'The recurrence change could not be completed. Reload the event and review the proposed changes.', 'mime-simple-events-calendar' ),
			array( 'status' => $status )
		);
	}

	/**
	 * Map canonical persistence failures without exposing storage details.
	 *
	 * @param RecurrencePersistenceResult $result Failed persistence result.
	 */
	private function persistence_error( RecurrencePersistenceResult $result ): WP_Error {
		$error  = $result->error() ?? RecurrencePersistenceError::STORAGE_FAILED;
		$status = match ( $error ) {
			RecurrencePersistenceError::INVALID_EVENT      => 404,
			RecurrencePersistenceError::FORBIDDEN          => 403,
			RecurrencePersistenceError::STALE_REVISION     => 409,
			RecurrencePersistenceError::IDENTITY_MISMATCH,
			RecurrencePersistenceError::TIMEZONE_MISMATCH  => 409,
			RecurrencePersistenceError::INDEX_GUARD_FAILED,
			RecurrencePersistenceError::STORAGE_FAILED,
			RecurrencePersistenceError::PROJECTION_FAILED  => 500,
		};

		return new WP_Error(
			'wpse_recurrence_' . $error->value,
			__( 'The recurrence change was not completed. No stale occurrence data will be shown.', 'mime-simple-events-calendar' ),
			array( 'status' => $status )
		);
	}
}
