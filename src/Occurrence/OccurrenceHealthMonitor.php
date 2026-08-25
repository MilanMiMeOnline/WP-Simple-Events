<?php
/**
 * Occurrence index health monitor.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Lifecycle\Installer;

/**
 * Converts schema, migration and bounded public coverage into one clear state.
 */
final readonly class OccurrenceHealthMonitor {
	/**
	 * Create the health monitor.
	 *
	 * @param OccurrenceTableLifecycle $table    Projection table lifecycle.
	 * @param OccurrenceCoverageProbe  $coverage Public-event coverage probe.
	 */
	public function __construct(
		private OccurrenceTableLifecycle $table = new OccurrenceTable(),
		private OccurrenceCoverageProbe $coverage = new WordPressOccurrenceCoverageProbe()
	) {}

	/**
	 * Return a fail-closed administrator status.
	 */
	public function status(): OccurrenceHealthStatus {
		if ( Installer::SCHEMA_VERSION !== get_option( Installer::VERSION_OPTION ) || ! $this->table->exists() ) {
			return OccurrenceHealthStatus::UNAVAILABLE;
		}

		if ( ! in_array(
			get_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, false ),
			array( true, 1, '1' ),
			true
		) ) {
			return OccurrenceHealthStatus::BUILDING;
		}

		return $this->coverage->has_public_gap()
			? OccurrenceHealthStatus::REPAIR_NEEDED
			: OccurrenceHealthStatus::HEALTHY;
	}
}
