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
use MiMe\WPSimpleEvents\Occurrence\OccurrenceRepairBatchProcessor;

/**
 * Handles administrator-only capability repair and bounded UTC reindexing.
 */
final readonly class EventMaintenanceController {
	public const REPAIR_CAPABILITIES_ACTION = 'wpse_repair_event_capabilities';
	public const REINDEX_ACTION             = 'wpse_reindex_event_dates';
	public const REPAIR_OCCURRENCES_ACTION  = 'wpse_repair_occurrence_index';

	private const MAX_COUNTER = 1_000_000_000;
	private const MAX_PAGE    = 1_000_000;

	/**
	 * Create the maintenance controller.
	 *
	 * @param RoleManager                    $roles       Event role manager.
	 * @param EventDateIndexBatchProcessor   $processor   Bounded UTC repair processor.
	 * @param OccurrenceRepairBatchProcessor $occurrences Bounded occurrence repair processor.
	 */
	public function __construct(
		private RoleManager $roles = new RoleManager(),
		private EventDateIndexBatchProcessor $processor = new EventDateIndexBatchProcessor(),
		private OccurrenceRepairBatchProcessor $occurrences = new OccurrenceRepairBatchProcessor()
	) {}

	/**
	 * Register authenticated maintenance handlers only.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::REPAIR_CAPABILITIES_ACTION, array( $this, 'repair_capabilities' ) );
		add_action( 'admin_post_' . self::REINDEX_ACTION, array( $this, 'reindex_events' ) );
		add_action( 'admin_post_' . self::REPAIR_OCCURRENCES_ACTION, array( $this, 'repair_occurrences' ) );
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
	 * Process one bounded public occurrence repair page.
	 */
	public function repair_occurrences(): void {
		$this->authorize( self::REPAIR_OCCURRENCES_ACTION );
		$offset    = $this->request_integer( 'wpse_occurrence_offset', 0, self::MAX_COUNTER );
		$processed = $this->request_integer( 'wpse_occurrence_processed', 0, self::MAX_COUNTER );
		$indexed   = $this->request_integer( 'wpse_occurrence_indexed', 0, self::MAX_COUNTER );
		$invalid   = $this->request_integer( 'wpse_occurrence_invalid', 0, self::MAX_COUNTER );
		$failed    = $this->request_integer( 'wpse_occurrence_failed', 0, self::MAX_COUNTER );
		$result    = $this->occurrences->process( $offset );

		$this->redirect(
			$result->has_more ? 'occurrence_repair_progress' : 'occurrence_repair_complete',
			array(
				'wpse_occurrence_offset'    => min(
					self::MAX_COUNTER,
					$offset + $result->skipped_invalid + $result->failed
				),
				'wpse_occurrence_processed' => min( self::MAX_COUNTER, $processed + $result->processed ),
				'wpse_occurrence_indexed'   => min( self::MAX_COUNTER, $indexed + $result->indexed ),
				'wpse_occurrence_invalid'   => min( self::MAX_COUNTER, $invalid + $result->skipped_invalid ),
				'wpse_occurrence_failed'    => min( self::MAX_COUNTER, $failed + $result->failed ),
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
				esc_html__( 'You are not allowed to run event maintenance.', 'mime-simple-events-calendar' ),
				esc_html__( 'Event maintenance denied', 'mime-simple-events-calendar' ),
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
