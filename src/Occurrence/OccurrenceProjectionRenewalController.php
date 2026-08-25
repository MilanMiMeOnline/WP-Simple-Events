<?php
/**
 * Scheduled recurring projection-window renewal.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Lifecycle\Installer;

/**
 * Keeps buffered recurrence coverage current without visitor-request work.
 */
final readonly class OccurrenceProjectionRenewalController {
	public const HOOK          = 'wpse_occurrence_projection_renewal';
	public const OFFSET_OPTION = 'wpse_occurrence_projection_renewal_offset';

	private const INITIAL_DELAY      = 3600;
	private const CONTINUATION_DELAY = 300;
	private const IDLE_DELAY         = 86400;

	/**
	 * Create the scheduled renewal controller.
	 *
	 * @param OccurrenceProjectionRenewalBatchProcessor $processor Bounded renewal processor.
	 */
	public function __construct(
		private OccurrenceProjectionRenewalBatchProcessor $processor = new OccurrenceProjectionRenewalBatchProcessor()
	) {}

	/** Register scheduling and worker hooks. */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule' ), 9 );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/** Ensure one maintenance pass is queued after schema and migration are ready. */
	public function schedule(): void {
		if ( ! $this->ready() || false !== wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::INITIAL_DELAY, self::HOOK );
	}

	/** Process one bounded page and retain only a numeric unresolved offset. */
	public function run(): void {
		if ( ! $this->ready() ) {
			delete_option( self::OFFSET_OPTION );
			return;
		}

		$stored_offset = get_option( self::OFFSET_OPTION, 0 );
		$offset        = is_numeric( $stored_offset ) ? max( 0, (int) $stored_offset ) : 0;
		$result        = $this->processor->process( $offset );

		if ( $result->has_more ) {
			update_option(
				self::OFFSET_OPTION,
				$offset + $result->skipped_invalid + $result->failed,
				false
			);
			$this->queue( self::CONTINUATION_DELAY );
			return;
		}

		delete_option( self::OFFSET_OPTION );
		$this->queue( self::IDLE_DELAY );
	}

	/** Determine whether background projection work is safe to run. */
	private function ready(): bool {
		return Installer::SCHEMA_VERSION === get_option( Installer::VERSION_OPTION )
			&& in_array(
				get_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, false ),
				array( true, 1, '1' ),
				true
			);
	}

	/**
	 * Queue one worker only when another request has not already done so.
	 *
	 * @param int $delay Delay in seconds.
	 */
	private function queue( int $delay ): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK );
		}
	}
}
