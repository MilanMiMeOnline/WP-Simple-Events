<?php
/**
 * Real-host Elementor compatibility inspector executed through WP-CLI.
 *
 * @package MiMe\WPSimpleEvents\Tests\Compatibility
 */

declare(strict_types=1);

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Fail the compatibility run with one actionable message.
 *
 * @param bool   $condition Required condition.
 * @param string $message   Failure message.
 */
function wpse_compat_require( bool $condition, string $message ): void {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
}

/** Execute the complete real-host widget contract. */
function wpse_run_elementor_compat_inspection(): void {
	wpse_compat_require( class_exists( Elementor\Plugin::class ), 'Elementor is not active.' );
	wpse_compat_require( did_action( 'wpse_loaded' ) > 0, 'WP Simple Events did not boot.' );

	$manager = Elementor\Plugin::$instance->widgets_manager;
	$widgets = $manager->get_widget_types();
	$names   = array(
		'wpse-event-list',
		'wpse-event-calendar',
		'wpse-event-details',
		'wpse-event-title',
		'wpse-event-featured-image',
		'wpse-event-date-time',
		'wpse-event-status',
		'wpse-event-venue',
		'wpse-event-address',
		'wpse-event-location-link',
		'wpse-event-content',
		'wpse-event-excerpt',
		'wpse-event-external-action',
		'wpse-event-categories',
		'wpse-event-tags',
	);

	foreach ( $names as $name ) {
		wpse_compat_require( isset( $widgets[ $name ] ), 'Missing Elementor widget: ' . $name );
		wpse_compat_require( in_array( 'wp-simple-events', $widgets[ $name ]->get_categories(), true ), 'Wrong widget category: ' . $name );
		wpse_compat_require( in_array( 'wpse-frontend', $widgets[ $name ]->get_style_depends(), true ), 'Missing style dependency: ' . $name );
		wpse_compat_require( false === $widgets[ $name ]->has_widget_inner_wrapper(), 'Optimized DOM disabled: ' . $name );
	}

	$control_contracts = array(
		'wpse-event-title'           => array( 'event_id', 'heading', 'link', 'text_color', 'spacing' ),
		'wpse-event-featured-image'  => array( 'event_id', 'image_size', 'alt_mode', 'link', 'spacing' ),
		'wpse-event-date-time'       => array( 'event_id', 'show_label', 'label', 'text_color', 'spacing' ),
		'wpse-event-status'          => array( 'event_id', 'text_color', 'spacing' ),
		'wpse-event-venue'           => array( 'event_id', 'show_label', 'label', 'text_color', 'spacing' ),
		'wpse-event-address'         => array( 'event_id', 'text_color', 'spacing' ),
		'wpse-event-location-link'   => array( 'event_id', 'link_text', 'text_color', 'spacing' ),
		'wpse-event-content'         => array( 'event_id', 'text_color', 'spacing' ),
		'wpse-event-excerpt'         => array( 'event_id', 'text_color', 'spacing' ),
		'wpse-event-external-action' => array( 'event_id', 'link_text', 'text_color', 'spacing' ),
		'wpse-event-categories'      => array( 'event_id', 'show_label', 'label', 'text_color', 'spacing' ),
		'wpse-event-tags'            => array( 'event_id', 'show_label', 'label', 'text_color', 'spacing' ),
	);

	foreach ( $control_contracts as $name => $required_controls ) {
		foreach ( $required_controls as $control ) {
			wpse_compat_require(
				null !== $widgets[ $name ]->get_controls( $control ),
				'Missing ' . $control . ' control on ' . $name
			);
		}

		wpse_compat_require( null === $widgets[ $name ]->get_controls( 'meta_key' ), 'Raw metadata control exposed on ' . $name );
	}

	$event_id = wp_insert_post(
		array(
			'post_type'   => EventPostType::POST_TYPE,
			'post_status' => 'draft',
			'post_title'  => 'Elementor compatibility event',
		)
	);
	wpse_compat_require( is_int( $event_id ) && $event_id > 0, 'Could not create compatibility event.' );

	$start = strtotime( '2026-07-20 12:00:00 UTC' );
	$end   = strtotime( '2026-07-20 14:00:00 UTC' );
	update_post_meta( $event_id, EventMeta::START_LOCAL, '2026-07-20T12:00' );
	update_post_meta( $event_id, EventMeta::END_LOCAL, '2026-07-20T14:00' );
	update_post_meta( $event_id, EventMeta::START_UTC, $start );
	update_post_meta( $event_id, EventMeta::END_UTC, $end );
	update_post_meta( $event_id, EventMeta::ALL_DAY, false );
	update_post_meta( $event_id, EventMeta::TIMEZONE, 'UTC' );
	update_post_meta( $event_id, EventMeta::VENUE, 'Compatibility Hall' );
	wp_update_post(
		array(
			'ID'          => $event_id,
			'post_status' => 'publish',
		)
	);
	wpse_compat_require( 'publish' === get_post_status( $event_id ), 'Compatibility event could not be published.' );

	$venue_class  = get_class( $widgets['wpse-event-venue'] );
	$venue_widget = new $venue_class(
		array(
			'id'         => 'wpsecompatvenue',
			'elType'     => 'widget',
			'widgetType' => 'wpse-event-venue',
			'settings'   => array( 'event_id' => (string) $event_id ),
		),
		array()
	);
	$render       = new ReflectionMethod( $venue_widget, 'render' );
	$render->setAccessible( true );
	ob_start();
	$render->invoke( $venue_widget );
	$output = (string) ob_get_clean();
	wpse_compat_require( str_contains( $output, 'Compatibility Hall' ), 'Selected public event did not render through the atomic venue widget.' );

	$invalid_widget = new $venue_class(
		array(
			'id'         => 'wpsecompatinvalid',
			'elType'     => 'widget',
			'widgetType' => 'wpse-event-venue',
			'settings'   => array( 'event_id' => 'not-an-event' ),
		),
		array()
	);
	$invalid_render = new ReflectionMethod( $invalid_widget, 'render' );
	$invalid_render->setAccessible( true );
	ob_start();
	$invalid_render->invoke( $invalid_widget );
	$invalid_output = (string) ob_get_clean();
	wpse_compat_require( '' === $invalid_output, 'Malformed event source leaked or fell back on the frontend.' );

	wp_delete_post( $event_id, true );
	WP_CLI::success( 'WP Simple Events Elementor compatibility contract passed.' );
}

wpse_run_elementor_compat_inspection();
