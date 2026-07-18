<?php
/**
 * Idempotent plugin installation and upgrade tasks.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Lifecycle;

use MiMe\WPSimpleEvents\Access\RoleManager;

/**
 * Keeps persistent role and schema state in sync.
 */
final class Installer {
	public const SCHEMA_VERSION = '1.0.0';
	public const VERSION_OPTION = 'wpse_schema_version';

	/**
	 * Run all current installation tasks idempotently.
	 */
	public function install(): void {
		( new RoleManager() )->grant();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
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
