<?php
/**
 * Plugin activation routine.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Lifecycle;

use MiMe\WPSimpleEvents\Content\ContentRegistry;

/**
 * Prepares roles and rewrite rules without deleting or migrating event data.
 */
final class Activator {
	/**
	 * Activate the plugin for a single WordPress site.
	 *
	 * @param bool $network_wide Whether this is a multisite network activation.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			wp_die(
				esc_html__( 'MiMe Simple Events and Calendar must currently be activated separately on each site in a multisite network.', 'mime-simple-events-calendar' )
			);
		}

		( new ContentRegistry() )->register();
		( new Installer() )->install();
		flush_rewrite_rules( false );
	}
}
