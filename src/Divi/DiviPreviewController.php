<?php
/**
 * Authenticated read-only Divi Visual Builder previews.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/** Exposes bounded composite HTML only to users who may edit the active post. */
final readonly class DiviPreviewController {
	public const REST_NAMESPACE        = 'wpse/v1';
	public const REST_ROUTE            = '/divi-preview';
	private const MAX_ATTRS_JSON_BYTES = 20000;

	/**
	 * Create the authenticated preview boundary.
	 *
	 * @param DiviCompositeModuleRenderer $renderer Shared native renderer adapter.
	 */
	public function __construct( private DiviCompositeModuleRenderer $renderer ) {}

	/** Register REST discovery after WordPress initializes its API. */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register one authenticated, read-only preview route. */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_preview' ),
				'args'                => array(
					'postId' => array(
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'validate_callback' => array( $this, 'valid_post_id' ),
					),
					'module' => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => array_values( DiviCompositeModuleRenderer::MODULES ),
						'validate_callback' => array( $this, 'valid_module' ),
					),
					'attrs'  => array(
						'type'              => 'object',
						'required'          => true,
						'validate_callback' => array( $this, 'valid_attrs' ),
					),
				),
			)
		);
	}

	/**
	 * Require an existing editor post and its exact edit capability.
	 *
	 * @param WP_REST_Request $request Validated REST request.
	 */
	public function can_preview( WP_REST_Request $request ): bool {
		$post_id = $this->post_id( $request->get_param( 'postId' ) );

		return $post_id > 0 && get_post( $post_id ) instanceof WP_Post && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Return escaped native HTML for one validated composite.
	 *
	 * @param WP_REST_Request $request Validated REST request.
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = $this->post_id( $request->get_param( 'postId' ) );
		$module  = $request->get_param( 'module' );
		$attrs   = $request->get_param( 'attrs' );

		if ( $post_id < 1 || ! $this->valid_module( $module ) || ! $this->valid_attrs( $attrs ) ) {
			return new WP_Error(
				'wpse_invalid_divi_preview',
				__( 'The Divi event preview request is invalid.', 'mime-simple-events-calendar' ),
				array( 'status' => 400 )
			);
		}

		$html = $this->renderer->render( (string) $module, $attrs, $post_id );

		return new WP_REST_Response(
			array(
				'html'  => $html,
				'empty' => '' === $html,
			)
		);
	}

	/**
	 * Validate a strict positive post identifier.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_post_id( mixed $value ): bool {
		return $this->post_id( $value ) > 0;
	}

	/**
	 * Validate one internal renderer name.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_module( mixed $value ): bool {
		return is_string( $value ) && in_array( $value, DiviCompositeModuleRenderer::MODULES, true );
	}

	/**
	 * Bound the complete nested Divi attribute transport before normalization.
	 *
	 * @param mixed $value Raw REST value.
	 */
	public function valid_attrs( mixed $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		$json = wp_json_encode( $value );

		return is_string( $json ) && strlen( $json ) <= self::MAX_ATTRS_JSON_BYTES;
	}

	/**
	 * Normalize only a strict positive decimal identifier.
	 *
	 * @param mixed $value Raw identifier.
	 */
	private function post_id( mixed $value ): int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return 0;
		}

		$string = trim( (string) $value );

		if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $string ) ) {
			return 0;
		}

		$post_id = filter_var( $string, FILTER_VALIDATE_INT );

		return false === $post_id ? 0 : $post_id;
	}
}
