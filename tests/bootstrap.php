<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

$wpse_autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $wpse_autoloader ) ) {
	throw new RuntimeException( 'Composer dependencies are missing. Run composer install.' );
}

require $wpse_autoloader;

require __DIR__ . '/Support/WPPost.php';
require __DIR__ . '/Support/WPError.php';
require __DIR__ . '/Support/WPQuery.php';
require __DIR__ . '/Support/Elementor/WidgetBase.php';
require __DIR__ . '/Support/Elementor/ControlsManager.php';
require __DIR__ . '/Support/Elementor/GroupControlTypography.php';
require __DIR__ . '/Support/Elementor/GroupControlBorder.php';
require __DIR__ . '/Support/Elementor/WidgetsManager.php';
require __DIR__ . '/Support/Elementor/ElementsManager.php';
require __DIR__ . '/Support/global-wordpress-functions.php';
require __DIR__ . '/Support/wordpress-functions.php';
