<?php
/**
 * Protected event maintenance actions.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Access\RoleManager;
use MiMe\WPSimpleEvents\Maintenance\EventDateIndexBatchProcessor;

/**
 * Handles administrator-only capability repair and bounded UTC reindexing.
 */
final readonly class EventMaintenanceController {
	public const REPAIR_CAPABILITIES_ACTION = 'wpse_repair_event_capabilities';
	public const REINDEX_ACTION             = 'wpse_reindex_event_dates';

	private const MAX_COUNTER = 1_000_000_000;
	private const MAX_PAGE    = 1_000_000;

	/**
	 * Create the maintenance controller.
	 *
	 * @param RoleManager                  $roles     Event role manager.
	 * @param EventDateIndexBatchProcessor $processor Bounded UTC repair processor.
	 */
	public function __construct(
		private RoleManager $roles = new RoleManager(),
		private EventDateIndexBatchProcessor $processor = new EventDateIndexBatchProcessor()
	) {}

	/**
	 * Register authenticated maintenance handlers only.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::REPAIR_CAPABILITIES_ACTION, array( $this, 'repair_capabilities' ) );
		add_action( 'admin_post_' . self::REINDEX_ACTION, array( $this, 'reindex_events' ) );
	}

	/**
	 * Restore the documented administrator/editor event capability set.
	 */
	public function repair_capabilities(): void {
		$this->authorize( self::REPAIR_CAPABILITIES_ACTION );
		$this->roles->grant();
		$this->redirect( 'capabilities_repaired' );
	}

	/**
	 * Process one bounded UTC-index page and return continuation state.
	 */
	public function reindex_events(): void {
		$this->authorize( self::REINDEX_ACTION );
		$page      = $this->request_integer( 'wpse_page', 1, self::MAX_PAGE );
		$processed = $this->request_integer( 'wpse_processed', 0, self::MAX_COUNTER );
		$changed   = $this->request_integer( 'wpse_changed', 0, self::MAX_COUNTER );
		$skipped   = $this->request_integer( 'wpse_skipped', 0, self::MAX_COUNTER );
		$failed    = $this->request_integer( 'wpse_failed', 0, self::MAX_COUNTER );
		$result    = $this->processor->process( $page );

		$this->redirect(
			$result->has_more ? 'reindex_progress' : 'reindex_complete',
			array(
				'wpse_page'      => $result->has_more ? $result->next_page : 1,
				'wpse_processed' => min( self::MAX_COUNTER, $processed + $result->processed ),
				'wpse_changed'   => min( self::MAX_COUNTER, $changed + $result->changed ),
				'wpse_skipped'   => min( self::MAX_COUNTER, $skipped + $result->skipped ),
				'wpse_failed'    => min( self::MAX_COUNTER, $failed + $result->failed ),
			)
		);
	}

	/**
	 * Require both administrator capability and action intent.
	 *
	 * @param string $action Nonce action.
	 */
	private function authorize( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to run event maintenance.', 'simple-events-by-mime' ),
				esc_html__( 'Event maintenance denied', 'simple-events-by-mime' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( $action );
	}

	/**
	 * Read one bounded non-negative POST counter.
	 *
	 * @param string $key      Request key.
	 * @param int    $fallback Fallback value.
	 * @param int    $maximum  Inclusive maximum.
	 */
	private function request_integer( string $key, int $fallback, int $maximum ): int {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies the action nonce before parsing continuation state.
			return $fallback;
		}

		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies the action nonce before parsing continuation state.

		if ( '' === $value || ! ctype_digit( $value ) ) {
			return $fallback;
		}

		return max( $fallback, min( $maximum, (int) $value ) );
	}

	/**
	 * Redirect to the settings page with allowlisted maintenance feedback.
	 *
	 * @param string             $status   Allowlisted status marker.
	 * @param array<string, int> $counters Optional continuation counters.
	 */
	private function redirect( string $status, array $counters = array() ): never {
		$url = add_query_arg( 'wpse_maintenance', $status, EventSettingsPage::url() );

		foreach ( $counters as $key => $value ) {
			$url = add_query_arg( $key, (string) $value, $url );
		}

		nocache_headers();
		wp_safe_redirect( $url );
		exit;
	}
}
