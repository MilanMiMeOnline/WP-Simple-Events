<?php
/**
 * Event role capability management.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Access;

/**
 * Grants the event capability contract to selected WordPress roles.
 */
final class RoleManager {
	/**
	 * Grant event capabilities idempotently.
	 */
	public function grant(): void {
		foreach ( EventCapabilities::editorial_roles() as $role_name ) {
			$role = get_role( $role_name );

			if ( null === $role ) {
				continue;
			}

			foreach ( EventCapabilities::editorial() as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}

	/**
	 * Revoke the capabilities this plugin grants to its selected roles.
	 */
	public function revoke(): void {
		foreach ( EventCapabilities::editorial_roles() as $role_name ) {
			$role = get_role( $role_name );

			if ( null === $role ) {
				continue;
			}

			foreach ( EventCapabilities::editorial() as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}
}
