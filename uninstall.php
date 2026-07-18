<?php
/**
 * WP Simple Events uninstall entry point.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wpse_autoloader = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $wpse_autoloader ) ) {
	return;
}

require $wpse_autoloader;

( new MiMe\WPSimpleEvents\Lifecycle\Uninstaller() )->run();
