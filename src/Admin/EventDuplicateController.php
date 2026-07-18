<?php
/**
 * Secured event duplication request controller.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Access\EventCapabilities;
use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Adds the row action and handles its nonce-protected admin request.
 */
final class EventDuplicateController {
	public const ACTION = 'wpse_duplicate_event';

	private const NOTICE_QUERY = 'wpse_duplicated';

	/**
	 * Create the controller.
	 *
	 * @param EventDuplicator $duplicator Allowlisted persistence service.
	 */
	public function __construct( private readonly EventDuplicator $duplicator = new EventDuplicator() ) {}

	/**
	 * Register list-row, endpoint and feedback hooks.
	 */
	public function register(): void {
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_action_' . self::ACTION, array( $this, 'duplicate' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Add the duplicate link only when the complete copy can be authorized.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param WP_Post               $post    Current post.
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, WP_Post $post ): array {
		if ( ! $this->can_duplicate( $post ) ) {
			return $actions;
		}

		$url = add_query_arg( 'action', self::ACTION, admin_url( 'admin.php' ) );
		$url = add_query_arg( 'post', (string) $post->ID, $url );
		$url = add_query_arg( '_wpnonce', wp_create_nonce( $this->nonce_action( $post->ID ) ), $url );

		$actions['wpse_duplicate'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Duplicate event', 'wp-simple-events' )
		);

		return $actions;
	}

	/**
	 * Handle one state-changing duplication request.
	 */
	public function duplicate(): void {
		$source_id = $this->request_post_id();
		$source    = $source_id > 0 ? get_post( $source_id ) : null;

		if ( ! $source instanceof WP_Post || ! $this->can_duplicate( $source ) ) {
			wp_die(
				esc_html__( 'You are not allowed to duplicate this event.', 'wp-simple-events' ),
				esc_html__( 'Event duplication denied', 'wp-simple-events' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( $this->nonce_action( $source_id ) );
		$new_id = $this->duplicator->duplicate( $source_id );

		if ( is_wp_error( $new_id ) ) {
			wp_die(
				esc_html( $new_id->get_error_message() ),
				esc_html__( 'Event duplication failed', 'wp-simple-events' ),
				array( 'response' => 500 )
			);
		}

		$edit_link = get_edit_post_link( $new_id, 'raw' );

		if ( ! is_string( $edit_link ) || '' === $edit_link ) {
			wp_die(
				esc_html__( 'The event was copied, but its editor URL is unavailable.', 'wp-simple-events' ),
				esc_html__( 'Event duplication incomplete', 'wp-simple-events' ),
				array( 'response' => 500 )
			);
		}

		$edit_link = add_query_arg( self::NOTICE_QUERY, '1', $edit_link );
		wp_safe_redirect( $edit_link );
		exit;
	}

	/**
	 * Render success guidance only on the copied event editor.
	 */
	public function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success marker after the protected duplication redirect.
		$notice = $_GET[ self::NOTICE_QUERY ] ?? '';

		if ( ! is_string( $notice ) || '1' !== sanitize_text_field( wp_unslash( $notice ) ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || EventPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Event duplicated as a draft. Review the copied dates before publishing; the external event URL was not copied.', 'wp-simple-events' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Require source edit, event creation and event-term assignment rights.
	 *
	 * @param WP_Post $post Source post.
	 */
	private function can_duplicate( WP_Post $post ): bool {
		return EventPostType::POST_TYPE === $post->post_type
			&& 'trash' !== $post->post_status
			&& current_user_can( 'edit_post', $post->ID )
			&& current_user_can( EventCapabilities::EDIT_POSTS )
			&& current_user_can( EventCapabilities::ASSIGN_TERMS );
	}

	/**
	 * Parse the source ID without accepting arrays or mixed input.
	 */
	private function request_post_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The parsed ID selects the nonce action verified before mutation.
		$value = $_GET['post'] ?? '';

		return is_string( $value ) ? absint( wp_unslash( $value ) ) : 0;
	}

	/**
	 * Build an event-specific nonce action.
	 *
	 * @param int $post_id Source event ID.
	 */
	private function nonce_action( int $post_id ): string {
		return self::ACTION . '_' . $post_id;
	}
}
