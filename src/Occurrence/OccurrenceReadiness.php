<?php
/**
 * Occurrence read-path readiness gate.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Lifecycle\Installer;

/**
 * Enables occurrence reads only after schema, migration and coverage agree.
 */
final class OccurrenceReadiness {
	/**
	 * Request-scoped memoized decision.
	 *
	 * @var bool|null
	 */
	private ?bool $ready = null;

	/**
	 * Create the request-scoped readiness gate.
	 *
	 * @param OccurrenceTableLifecycle $table    Projection table lifecycle.
	 * @param OccurrenceCoverageProbe  $coverage Public-event coverage probe.
	 */
	public function __construct(
		private readonly OccurrenceTableLifecycle $table = new OccurrenceTable(),
		private readonly OccurrenceCoverageProbe $coverage = new WordPressOccurrenceCoverageProbe()
	) {}

	/**
	 * Determine readiness once per request; any uncertainty fails closed.
	 */
	public function ready(): bool {
		if ( null !== $this->ready ) {
			return $this->ready;
		}

		$migration_complete = in_array(
			get_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, false ),
			array( true, 1, '1' ),
			true
		);

		$this->ready = Installer::SCHEMA_VERSION === get_option( Installer::VERSION_OPTION )
			&& $migration_complete
			&& $this->table->exists()
			&& ! $this->coverage->has_public_gap();

		return $this->ready;
	}
}
