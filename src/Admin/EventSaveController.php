<?php
/**
 * Native event editor save controller.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Access\EventCapabilities;
use MiMe\WPSimpleEvents\Application\EventInputMapper;
use MiMe\WPSimpleEvents\Application\EventPersistence;
use MiMe\WPSimpleEvents\Application\EventPublicationPolicy;
use MiMe\WPSimpleEvents\Application\EventValidationError;
use MiMe\WPSimpleEvents\Application\EventValidationMessages;
use MiMe\WPSimpleEvents\Application\EventValidator;
use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Validates classic/meta-box writes and prevents invalid publication.
 */
final class EventSaveController {
	/**
	 * Errors collected during the current editor request.
	 *
	 * @var array<string, EventValidationError>
	 */
	private array $request_errors = array();

	/**
	 * Whether the current native write failed validation before insertion.
	 *
	 * @var bool
	 */
	private bool $native_write_invalid = false;

	/**
	 * Create the native save controller.
	 *
	 * @param EventInputMapper        $mapper      WordPress input mapper.
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
	 * Register classic editor hooks.
	 */
	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'guard_publication' ), 10, 2 );
		add_action( 'save_post_' . EventPostType::POST_TYPE, array( $this, 'save' ), 10, 3 );
		add_filter( 'redirect_post_location', array( $this, 'add_errors_to_redirect' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Downgrade an invalid publication attempt to a draft before database write.
	 *
	 * @param array<string, mixed> $data    Slashed, processed post data.
	 * @param array<string, mixed> $postarr Sanitized post input.
	 * @return array<string, mixed>
	 */
	public function guard_publication( array $data, array $postarr ): array {
		if ( EventPostType::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		if ( $this->skip_native_context() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $data;
		}

		$post_id     = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$post_status = is_string( $data['post_status'] ?? null ) ? $data['post_status'] : 'draft';
		$payload     = $this->authorized_payload( $post_id );

		if ( null === $payload ) {
			if ( ! $this->policy->requires_date_range( $post_status ) ) {
				return $data;
			}

			$result = $this->validator->validate( $this->mapper->from_rest( array(), $post_id ), true );
		} else {
			$result = $this->validator->validate(
				$this->mapper->from_admin( $payload, $post_id ),
				$this->policy->requires_date_range( $post_status )
			);
		}

		if ( $result->is_valid() ) {
			return $data;
		}

		$this->record_errors( $result->errors() );
		$this->native_write_invalid = true;

		if ( $this->policy->requires_date_range( $post_status ) ) {
			$data['post_status'] = 'draft';
		}

		return $data;
	}

	/**
	 * Persist validated native editor fields.
	 *
	 * @param int     $post_id Event post ID.
	 * @param WP_Post $post    Saved event post.
	 * @param bool    $update  Whether an existing post was updated.
	 */
	public function save( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if ( $this->native_write_invalid ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$payload = $this->authorized_payload( $post_id );

		if ( null === $payload ) {
			return;
		}

		$result = $this->validator->validate(
			$this->mapper->from_admin( $payload, $post_id ),
			$this->policy->requires_date_range( $post->post_status )
		);

		if ( ! $result->is_valid() ) {
			$this->record_errors( $result->errors() );
			return;
		}

		$data = $result->data();

		if ( null !== $data ) {
			$this->persistence->persist( $post_id, $data );
		}
	}

	/**
	 * Carry only allowlisted error codes across the post-save redirect.
	 *
	 * @param string $location Existing redirect URL.
	 */
	public function add_errors_to_redirect( string $location ): string {
		if ( array() === $this->request_errors ) {
			return $location;
		}

		return add_query_arg(
			'wpse_validation',
			implode( ',', array_keys( $this->request_errors ) ),
			$location
		);
	}

	/**
	 * Render actionable validation errors on the event editor.
	 */
	public function render_notice(): void {
		$errors = $this->errors_from_query();

		if ( array() === $errors ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || EventPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'The event details were not saved because they need attention.', 'wp-simple-events' ); ?></strong></p>
			<ul>
				<?php foreach ( $this->messages->messages( $errors ) as $message ) : ?>
					<li><?php echo esc_html( $message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Read and authorize the current native editor request.
	 *
	 * Return the verified and unslashed editor payload.
	 *
	 * @param int $post_id Existing event ID, or zero for a new event.
	 * @return array<string, mixed>|null
	 */
	private function authorized_payload( int $post_id ): ?array {
		if ( $this->skip_native_context() ) {
			return null;
		}

		if ( ! isset( $_POST[ EventMetaBox::NONCE_NAME ] ) || ! is_string( $_POST[ EventMetaBox::NONCE_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence is checked immediately before verification.
			return null;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ EventMetaBox::NONCE_NAME ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The unslashed value is verified on the next line.

		if ( ! wp_verify_nonce( $nonce, EventMetaBox::NONCE_ACTION ) ) {
			return null;
		}

		$can_edit = $post_id > 0
			? current_user_can( 'edit_post', $post_id )
			: current_user_can( EventCapabilities::EDIT_POSTS );

		if ( ! $can_edit || ! isset( $_POST['wpse_event'] ) || ! is_array( $_POST['wpse_event'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce was verified above.
			return null;
		}

		$raw_payload = wp_unslash( $_POST['wpse_event'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce was verified above.
		$payload     = array();

		foreach ( $raw_payload as $key => $value ) {
			if ( is_string( $key ) ) {
				$payload[ $key ] = $value;
			}
		}

		return $payload;
	}

	/**
	 * Whether WordPress is saving outside the authoritative site context.
	 */
	private function skip_native_context(): bool {
		return ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ( is_multisite() && ms_is_switched() );
	}

	/**
	 * Store unique stable errors for the save redirect.
	 *
	 * @param EventValidationError[] $errors Validation errors.
	 */
	private function record_errors( array $errors ): void {
		foreach ( $errors as $error ) {
			$this->request_errors[ $error->value ] = $error;
		}
	}

	/**
	 * Parse only known error codes from the current query string.
	 *
	 * @return EventValidationError[]
	 */
	private function errors_from_query(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only query value; every code is allowlisted below.
		if ( ! isset( $_GET['wpse_validation'] ) || ! is_string( $_GET['wpse_validation'] ) ) {
			return array();
		}

		$raw = sanitize_text_field( wp_unslash( $_GET['wpse_validation'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$errors = array();

		foreach ( explode( ',', $raw ) as $code ) {
			$error = EventValidationError::tryFrom( $code );

			if ( null !== $error ) {
				$errors[ $error->value ] = $error;
			}
		}

		return array_values( $errors );
	}
}
