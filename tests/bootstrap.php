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
require __DIR__ . '/Support/WPBlock.php';
require __DIR__ . '/Support/WPTerm.php';
require __DIR__ . '/Support/WPError.php';
require __DIR__ . '/Support/WPRESTRequest.php';
require __DIR__ . '/Support/WPRESTResponse.php';
require __DIR__ . '/Support/WPSitemapsProvider.php';
require __DIR__ . '/Support/WPQuery.php';
require __DIR__ . '/Support/FakeOccurrenceTable.php';
require __DIR__ . '/Support/FakeOccurrenceReadGateway.php';
require __DIR__ . '/Support/FakeOccurrenceGenerationCleaner.php';
require __DIR__ . '/Support/FakeOccurrenceCoverageProbe.php';
require __DIR__ . '/Support/FakeOccurrenceProjectionStore.php';
require __DIR__ . '/Support/FakeEventOccurrenceProjector.php';
require __DIR__ . '/Support/FakeRecurrenceAggregateStore.php';
require __DIR__ . '/Support/FakeRecurringEventOccurrenceProjector.php';
require __DIR__ . '/Support/FakeOccurrencePresentationProvider.php';
require __DIR__ . '/Support/FakeProjectedOccurrencePresentationProvider.php';
require __DIR__ . '/Support/Elementor/WidgetBase.php';
require __DIR__ . '/Support/Elementor/ControlsManager.php';
require __DIR__ . '/Support/Elementor/GroupControlTypography.php';
require __DIR__ . '/Support/Elementor/GroupControlBorder.php';
require __DIR__ . '/Support/Elementor/WidgetsManager.php';
require __DIR__ . '/Support/Elementor/ElementsManager.php';
require __DIR__ . '/Support/global-wordpress-functions.php';
require __DIR__ . '/Support/wordpress-functions.php';
