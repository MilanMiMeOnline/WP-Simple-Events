<?php
/**
 * Plugin Name:       Simple Events by MiMe
 * Description:       A lightweight, native events plugin for WordPress.
 * Version:           0.2.1
 * Requires at least: 6.9
 * Requires PHP:      8.3
 * Author:            MiMe
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-events-by-mime
 * Domain Path:       /languages
 * Elementor tested up to: 4.1.5
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPSE_VERSION', '0.2.1' );
define( 'WPSE_PLUGIN_FILE', __FILE__ );
define( 'WPSE_PLUGIN_DIR', __DIR__ );

$wpse_autoloader = WPSE_PLUGIN_DIR . '/vendor/autoload.php';

if ( ! is_readable( $wpse_autoloader ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Simple Events by MiMe could not start because its required files are missing. Install a complete release package.', 'simple-events-by-mime' )
			);
		}
	);

	return;
}

require $wpse_autoloader;

register_activation_hook( WPSE_PLUGIN_FILE, array( MiMe\WPSimpleEvents\Lifecycle\Activator::class, 'activate' ) );
register_deactivation_hook( WPSE_PLUGIN_FILE, array( MiMe\WPSimpleEvents\Lifecycle\Deactivator::class, 'deactivate' ) );

( new MiMe\WPSimpleEvents\Plugin() )->register();
