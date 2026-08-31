<?php
/**
 * Plugin-owned scheduled maintenance lifecycle.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Lifecycle;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceGenerationCleanupController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionRenewalController;

/**
 * Removes every scheduled callback that would be orphaned without the plugin.
 */
final class ScheduledMaintenance {
	/**
	 * Clear current-site jobs and their disposable continuation cursor.
	 */
	public static function clear(): void {
		wp_clear_scheduled_hook( OccurrenceIndexMigrationController::HOOK );
		wp_clear_scheduled_hook( OccurrenceGenerationCleanupController::HOOK );
		wp_clear_scheduled_hook( OccurrenceProjectionRenewalController::HOOK );
		delete_option( OccurrenceProjectionRenewalController::OFFSET_OPTION );
	}
}
