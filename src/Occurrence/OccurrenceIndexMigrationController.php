<?php
/**
 * Background one-off occurrence migration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Lifecycle\Installer;

/**
 * Schedules bounded batches without making visitor reads depend on WP-Cron.
 */
final class OccurrenceIndexMigrationController {
	public const HOOK            = 'wpse_occurrence_index_migrate';
	public const COMPLETE_OPTION = 'wpse_occurrence_index_migration_complete';

	private const RETRY_DELAY_SECONDS = 30;

	/**
	 * Create the migration controller.
	 *
	 * @param OccurrenceIndexBatchProcessor $processor Bounded batch processor.
	 */
	public function __construct(
		private readonly OccurrenceIndexBatchProcessor $processor = new OccurrenceIndexBatchProcessor()
	) {}

	/**
	 * Register scheduling and worker hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule' ), 7 );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Ensure exactly one bounded worker is queued after schema installation.
	 */
	public function schedule(): void {
		if ( Installer::SCHEMA_VERSION !== get_option( Installer::VERSION_OPTION ) ) {
			return;
		}

		if ( $this->is_complete() ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}

		if ( false !== wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::RETRY_DELAY_SECONDS, self::HOOK );
	}

	/**
	 * Process one page and queue another only while candidates remain.
	 */
	public function run(): void {
		if ( Installer::SCHEMA_VERSION !== get_option( Installer::VERSION_OPTION ) ) {
			return;
		}

		$result = $this->processor->process();

		if ( $result->has_more ) {
			wp_schedule_single_event( time() + self::RETRY_DELAY_SECONDS, self::HOOK );
			return;
		}

		update_option( self::COMPLETE_OPTION, true, false );
	}

	/**
	 * Normalize WordPress' supported persisted boolean representations.
	 */
	private function is_complete(): bool {
		return in_array( get_option( self::COMPLETE_OPTION, false ), array( true, 1, '1' ), true );
	}
}
