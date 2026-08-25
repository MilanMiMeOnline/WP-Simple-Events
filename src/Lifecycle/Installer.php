<?php
/**
 * Idempotent plugin installation and upgrade tasks.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Lifecycle;

use MiMe\WPSimpleEvents\Access\RoleManager;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceTable;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceTableLifecycle;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Routing\EventArchiveRewriteManager;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;

/**
 * Keeps persistent role and schema state in sync.
 */
final class Installer {
	public const SCHEMA_VERSION = '2.1.0';
	public const VERSION_OPTION = 'wpse_schema_version';

	/**
	 * Create the installer with an isolated occurrence-table lifecycle boundary.
	 *
	 * @param OccurrenceTableLifecycle $occurrences Derived occurrence table.
	 */
	public function __construct(
		private readonly OccurrenceTableLifecycle $occurrences = new OccurrenceTable()
	) {}

	/**
	 * Run all current installation tasks idempotently.
	 */
	public function install(): bool {
		$schema_changed = self::SCHEMA_VERSION !== get_option( self::VERSION_OPTION );

		if ( ! $this->occurrences->install() ) {
			return false;
		}

		( new RoleManager() )->grant();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );

		if ( $schema_changed ) {
			delete_option( OccurrenceIndexMigrationController::COMPLETE_OPTION );
			update_option(
				EventArchiveRewriteManager::PENDING_OPTION,
				( new EventArchiveSettings() )->slug(),
				false
			);
		}

		return true;
	}

	/**
	 * Run installation tasks only when the stored schema version differs.
	 */
	public function maybe_upgrade(): void {
		if ( self::SCHEMA_VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		$this->install();
	}
}
