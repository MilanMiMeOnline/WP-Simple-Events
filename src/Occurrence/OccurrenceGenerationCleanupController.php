<?php
/**
 * Scheduled inactive occurrence-generation cleanup.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Lifecycle\Installer;

/**
 * Keeps maintenance work off visitor requests and within a fixed batch size.
 */
final readonly class OccurrenceGenerationCleanupController {
	public const HOOK = 'wpse_occurrence_generation_cleanup';

	private const BATCH_SIZE          = 100;
	private const MINIMUM_AGE_SECONDS = 86400;
	private const INITIAL_DELAY       = 3600;
	private const CONTINUATION_DELAY  = 300;
	private const IDLE_DELAY          = 86400;

	/**
	 * Create the cleanup scheduler.
	 *
	 * @param OccurrenceGenerationCleaner $cleaner Bounded derived-row cleaner.
	 */
	public function __construct(
		private OccurrenceGenerationCleaner $cleaner = new WordPressOccurrenceGenerationCleaner()
	) {}

	/** Register scheduling and worker hooks. */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule' ), 8 );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/** Ensure one maintenance pass is queued after the current schema is ready. */
	public function schedule(): void {
		if ( Installer::SCHEMA_VERSION !== get_option( Installer::VERSION_OPTION )
			|| false !== wp_next_scheduled( self::HOOK )
		) {
			return;
		}

		wp_schedule_single_event( time() + self::INITIAL_DELAY, self::HOOK );
	}

	/** Run one bounded batch and schedule either a continuation or idle pass. */
	public function run(): void {
		if ( Installer::SCHEMA_VERSION !== get_option( Installer::VERSION_OPTION ) ) {
			return;
		}

		$removed = $this->cleaner->clean_before(
			time() - self::MINIMUM_AGE_SECONDS,
			self::BATCH_SIZE
		);
		$delay   = null === $removed || self::BATCH_SIZE === $removed
			? self::CONTINUATION_DELAY
			: self::IDLE_DELAY;

		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK );
		}
	}
}
