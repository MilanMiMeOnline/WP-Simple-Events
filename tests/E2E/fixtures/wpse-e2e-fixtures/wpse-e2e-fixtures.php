<?php
/**
 * Plugin Name: WP Simple Events E2E Fixtures
 * Description: Deterministic test-only fixtures for the browser regression suite.
 * Version:     1.0.0
 * Author:      MiMe
 * License:     GPL-2.0-or-later
 *
 * @package MiMe\WPSimpleEvents\Tests\E2E
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Seed the public calendar page used by Playwright. */
function wpse_e2e_seed_calendar_page(): void {
	wpse_e2e_insert_page(
		'wpse-e2e-calendar',
		'Calendar Browser Harness',
		'[wpse_calendar category="wpse-e2e-empty" filters="false"]'
	);
	wpse_e2e_insert_page(
		'wpse-e2e-calendar-filters',
		'Calendar Filter Harness',
		'[wpse_calendar category="wpse-e2e-category" filters="true"]'
	);
	wpse_e2e_insert_page(
		'wpse-e2e-calendar-multiple',
		'Multiple Calendar Harness',
		'[wpse_calendar category="wpse-e2e-category" filters="false"][wpse_calendar tag="wpse-e2e-tag" filters="false"]'
	);
	wpse_e2e_insert_page(
		'wpse-e2e-calendar-hidden',
		'Hidden Calendar Harness',
		'[wpse_e2e_hidden_calendar]'
	);
	wpse_e2e_insert_page(
		'wpse-e2e-calendar-wall-time',
		'Calendar Wall-time Harness',
		'[wpse_calendar category="wpse-e2e-wall-time" filters="false"]'
	);
	wpse_e2e_insert_page(
		'wpse-e2e-calendar-time-24',
		'Calendar 24-hour Harness',
		'[wpse_e2e_calendar_time]'
	);
	wpse_e2e_insert_page(
		'wpse-e2e-calendar-time-12',
		'Calendar 12-hour Harness',
		'[wpse_e2e_calendar_time]'
	);

	if ( ! current_user_can( MiMe\WPSimpleEvents\Access\EventCapabilities::EDIT_POSTS ) ) {
		return;
	}

	if ( get_option( 'wpse_e2e_events_seeded_v2', false ) ) {
		return;
	}

	$empty_category = wpse_e2e_term_id( 'wpse_event_category', 'E2E Empty', 'wpse-e2e-empty' );
	$category_only  = wpse_e2e_term_id( 'wpse_event_category', 'E2E Category', 'wpse-e2e-category' );
	$tag_only       = wpse_e2e_term_id( 'wpse_event_tag', 'E2E Tag', 'wpse-e2e-tag' );
	$wall_time      = wpse_e2e_term_id( 'wpse_event_category', 'E2E Wall Time', 'wpse-e2e-wall-time' );

	if ( 0 === $empty_category || 0 === $category_only || 0 === $tag_only || 0 === $wall_time ) {
		return;
	}

	wpse_e2e_insert_event(
		'wpse-e2e-same-day',
		'E2E Same-day event',
		'2026-08-10T12:00:00',
		'2026-08-10T22:00:00',
		false,
		'Europe/Brussels',
		'scheduled',
		array( $category_only ),
		array()
	);
	wpse_e2e_insert_event(
		'wpse-e2e-overnight',
		'E2E Overnight event',
		'2026-08-11T22:00:00',
		'2026-08-12T02:00:00',
		false,
		'Europe/Brussels',
		'scheduled',
		array(),
		array( $tag_only )
	);
	wpse_e2e_insert_event(
		'wpse-e2e-multi-day',
		'E2E Multi-day event',
		'2026-08-13T09:00:00',
		'2026-08-15T17:00:00',
		false,
		'+05:30',
		'postponed',
		array( $category_only ),
		array( $tag_only )
	);
	wpse_e2e_insert_event(
		'wpse-e2e-all-day',
		'E2E All-day event',
		'2026-08-16',
		'2026-08-18',
		true,
		'Europe/Brussels',
		'cancelled',
		array( $category_only ),
		array( $tag_only )
	);
	wpse_e2e_insert_event(
		'wpse-e2e-wall-utc',
		'E2E UTC same-day event',
		'2026-08-10T12:05:00',
		'2026-08-10T22:05:00',
		false,
		'+00:00',
		'scheduled',
		array( $wall_time ),
		array()
	);
	wpse_e2e_insert_event(
		'wpse-e2e-wall-positive',
		'E2E positive-offset event',
		'2026-08-01T00:30:00',
		'2026-08-01T01:30:00',
		false,
		'+14:00',
		'scheduled',
		array( $wall_time ),
		array()
	);
	wpse_e2e_insert_event(
		'wpse-e2e-wall-negative',
		'E2E negative-offset event',
		'2026-08-31T22:30:00',
		'2026-08-31T23:30:00',
		false,
		'-14:00',
		'scheduled',
		array( $wall_time ),
		array()
	);

	// Keep hide_empty filter fixtures deterministic after same-request seeding.
	wp_update_term_count( array( $empty_category, $category_only, $wall_time ), 'wpse_event_category', true );
	wp_update_term_count( array( $tag_only ), 'wpse_event_tag', true );

	$event_slugs = array(
		'wpse-e2e-same-day',
		'wpse-e2e-overnight',
		'wpse-e2e-multi-day',
		'wpse-e2e-all-day',
		'wpse-e2e-wall-utc',
		'wpse-e2e-wall-positive',
		'wpse-e2e-wall-negative',
	);
	$published   = array_filter(
		$event_slugs,
		static function ( string $slug ): bool {
			$event = get_page_by_path( $slug, OBJECT, 'wpse_event' );

			return $event instanceof WP_Post && 'publish' === $event->post_status;
		}
	);

	if ( count( $event_slugs ) === count( $published ) ) {
		update_option( 'wpse_e2e_events_seeded_v2', true, false );
	}
}

/**
 * Render a calendar inside an initially hidden integration container.
 *
 * @return string Test-only calendar markup.
 */
function wpse_e2e_render_hidden_calendar(): string {
	return '<div data-wpse-e2e-hidden-calendar hidden>'
		. do_shortcode( '[wpse_calendar category="wpse-e2e-empty" filters="false"]' )
		. '</div>';
}

/**
 * Render the shared calendar under one temporary WordPress time format.
 *
 * @param string $time_format WordPress/PHP time format.
 * @return string Test-only calendar markup.
 */
function wpse_e2e_render_calendar_with_time_format( string $time_format ): string {
	$callback = static fn (): string => $time_format;

	add_filter( 'pre_option_time_format', $callback );

	try {
		return do_shortcode( '[wpse_calendar category=wpse-e2e-wall-time filters=false initial_view=list]' );
	} finally {
		remove_filter( 'pre_option_time_format', $callback );
	}
}

/** Render the calendar with the format selected by its fixture page. */
function wpse_e2e_render_calendar_time(): string {
	$slug        = get_post_field( 'post_name', get_queried_object_id() );
	$time_format = is_string( $slug ) && str_ends_with( $slug, '-12' ) ? 'g:i a' : 'H:i';

	return wpse_e2e_render_calendar_with_time_format( $time_format );
}

/** Register test-only shortcodes before fixture pages are seeded. */
function wpse_e2e_register_shortcodes(): void {
	add_shortcode( 'wpse_e2e_hidden_calendar', 'wpse_e2e_render_hidden_calendar' );
	add_shortcode( 'wpse_e2e_calendar_time', 'wpse_e2e_render_calendar_time' );
}

/**
 * Create or find one deterministic test taxonomy term.
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $name     Term name.
 * @param string $slug     Term slug.
 */
function wpse_e2e_term_id( string $taxonomy, string $name, string $slug ): int {
	$existing = term_exists( $slug, $taxonomy );

	if ( is_array( $existing ) ) {
		return (int) $existing['term_id'];
	}

	$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );

	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

/**
 * Create one reusable event boundary fixture.
 *
 * @param string $slug       Event slug.
 * @param string $title      Event title.
 * @param string $start      Canonical local start.
 * @param string $end        Canonical local end.
 * @param bool   $all_day    Whether this is an all-day event.
 * @param string $timezone   IANA or fixed-offset timezone.
 * @param string $status     Event status.
 * @param int[]  $categories Category term IDs.
 * @param int[]  $tags       Tag term IDs.
 */
function wpse_e2e_insert_event(
	string $slug,
	string $title,
	string $start,
	string $end,
	bool $all_day,
	string $timezone,
	string $status,
	array $categories,
	array $tags
): void {
	$existing = get_page_by_path( $slug, OBJECT, 'wpse_event' );

	if ( $existing instanceof WP_Post ) {
		if ( 'publish' === $existing->post_status ) {
			return;
		}

		wp_delete_post( $existing->ID, true );
	}

	$range         = MiMe\WPSimpleEvents\Domain\EventDateRange::from_local( $start, $end, $all_day, $timezone );
	$previous_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test fixture preserves the request before injecting its own verified nonce.
	$_POST[ MiMe\WPSimpleEvents\Admin\EventMetaBox::NONCE_NAME ] = wp_create_nonce(
		MiMe\WPSimpleEvents\Admin\EventMetaBox::NONCE_ACTION
	);
	$_POST['wpse_event'] = array(
		'start_date' => substr( $start, 0, 10 ),
		'start_time' => $all_day ? '' : substr( $start, 11, 5 ),
		'end_date'   => substr( $end, 0, 10 ),
		'end_time'   => $all_day ? '' : substr( $end, 11, 5 ),
		'all_day'    => $all_day ? '1' : '0',
		'status'     => $status,
	);

	try {
		$post = wp_insert_post(
			array(
				'post_type'   => 'wpse_event',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => 'publish',
				'meta_input'  => array(
					'_wpse_start_local'  => $range->start_local(),
					'_wpse_end_local'    => $range->end_local(),
					'_wpse_start_utc'    => $range->start_utc(),
					'_wpse_end_utc'      => $range->end_utc(),
					'_wpse_all_day'      => $range->all_day(),
					'_wpse_timezone'     => $range->timezone(),
					'_wpse_event_status' => $status,
				),
			),
			true
		);
	} finally {
		$_POST = $previous_post;
	}

	if ( is_wp_error( $post ) ) {
		return;
	}

	wp_set_object_terms( $post, $categories, 'wpse_event_category' );
	wp_set_object_terms( $post, $tags, 'wpse_event_tag' );
}

/**
 * Create one deterministic test page.
 *
 * @param string $slug    Page slug.
 * @param string $title   Page title.
 * @param string $content Page content.
 */
function wpse_e2e_insert_page( string $slug, string $title, string $content ): void {
	if ( get_page_by_path( $slug ) instanceof WP_Post ) {
		return;
	}

	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
		),
		true
	);
}

register_activation_hook( __FILE__, 'wpse_e2e_seed_calendar_page' );
add_action( 'init', 'wpse_e2e_register_shortcodes', 5 );
add_action( 'init', 'wpse_e2e_seed_calendar_page', 20 );
