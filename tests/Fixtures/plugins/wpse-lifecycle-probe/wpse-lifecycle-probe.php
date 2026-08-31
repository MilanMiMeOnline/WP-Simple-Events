<?php
/**
 * Plugin Name: MiMe Simple Events and Calendar Lifecycle Probe
 * Description: Test-only authenticated install, upgrade and uninstall fixture.
 * Version:     1.0.0
 * Author:      MiMe
 * License:     GPL-2.0-or-later
 *
 * @package MiMe\WPSimpleEvents\Tests\Fixtures
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WPSE_LIFECYCLE_PROBE_PLUGIN = 'mime-simple-events-calendar/mime-simple-events-calendar.php';
const WPSE_LIFECYCLE_PROBE_MARKER = '_wpse_lifecycle_probe';
const WPSE_LIFECYCLE_PROBE_TOKEN  = 'wpse-upgrade-lifecycle-6f1d0d84e9d14b2da44657001847805f';

/** Register private test-only lifecycle routes. */
function wpse_lifecycle_probe_register_routes(): void {
	$routes = array(
		'/seed'           => array( 'POST', 'wpse_lifecycle_probe_seed' ),
		'/snapshot'       => array( 'GET', 'wpse_lifecycle_probe_snapshot_response' ),
		'/run-migration'  => array( 'POST', 'wpse_lifecycle_probe_run_migration' ),
		'/drop-table'     => array( 'POST', 'wpse_lifecycle_probe_drop_table' ),
		'/deactivate'     => array( 'POST', 'wpse_lifecycle_probe_deactivate' ),
		'/activate'       => array( 'POST', 'wpse_lifecycle_probe_activate' ),
		'/uninstall'      => array( 'POST', 'wpse_lifecycle_probe_uninstall' ),
		'/purge-fixtures' => array( 'POST', 'wpse_lifecycle_probe_purge_fixtures' ),
	);

	foreach ( $routes as $route => $definition ) {
		list( $method, $callback ) = $definition;
		register_rest_route(
			'wpse-lifecycle/v1',
			$route,
			array(
				'methods'             => $method,
				'callback'            => $callback,
				'permission_callback' => 'wpse_lifecycle_probe_authorize',
			)
		);
	}
}
add_action( 'rest_api_init', 'wpse_lifecycle_probe_register_routes' );

/**
 * Permit a site administrator or the isolated upgrade-runner token.
 *
 * @param WP_REST_Request $request Lifecycle request.
 */
function wpse_lifecycle_probe_authorize( WP_REST_Request $request ): bool {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	if ( ! defined( 'WPSE_LIFECYCLE_PROBE_TOKEN' ) ) {
		return false;
	}

	$expected = (string) WPSE_LIFECYCLE_PROBE_TOKEN;
	$provided = (string) $request->get_header( 'X-WPSE-Lifecycle-Token' );

	return '' !== $expected && hash_equals( $expected, $provided );
}

/**
 * Seed representative canonical data for one historical release.
 *
 * @param WP_REST_Request $request Authenticated request.
 */
function wpse_lifecycle_probe_seed( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$version = sanitize_text_field( (string) $request->get_param( 'version' ) );

	if ( ! defined( 'WPSE_VERSION' ) || WPSE_VERSION !== $version ) {
		return new WP_Error(
			'wpse_lifecycle_wrong_version',
			esc_html__( 'The requested historical plugin version is not active.', 'mime-simple-events-calendar' ),
			array( 'status' => 409 )
		);
	}

	if ( ! post_type_exists( 'wpse_event' ) || ! taxonomy_exists( 'wpse_event_category' ) || ! taxonomy_exists( 'wpse_event_tag' ) ) {
		return new WP_Error(
			'wpse_lifecycle_content_unavailable',
			esc_html__( 'The historical event content types are unavailable.', 'mime-simple-events-calendar' ),
			array( 'status' => 500 )
		);
	}

	$category = wp_insert_term( 'Upgrade category', 'wpse_event_category', array( 'slug' => 'wpse-upgrade-category' ) );
	$tag      = wp_insert_term( 'Upgrade tag', 'wpse_event_tag', array( 'slug' => 'wpse-upgrade-tag' ) );

	if ( is_wp_error( $category ) || is_wp_error( $tag ) ) {
		return new WP_Error(
			'wpse_lifecycle_term_failure',
			esc_html__( 'The upgrade taxonomy fixtures could not be created.', 'mime-simple-events-calendar' ),
			array( 'status' => 500 )
		);
	}

	$category_id = (int) $category['term_id'];
	$tag_id      = (int) $tag['term_id'];
	update_option( 'wpse_lifecycle_probe_term_ids', array( $category_id, $tag_id ), false );

	$start = new DateTimeImmutable( '+7 days 19:00:00', wp_timezone() );
	$end   = $start->modify( '+2 hours' );
	$event = wpse_lifecycle_probe_insert_event(
		'wpse-upgrade-one-off',
		'Upgrade one-off event',
		$start,
		$end,
		$category_id,
		$tag_id
	);

	if ( is_wp_error( $event ) ) {
		return $event;
	}

	$recurring_id = 0;

	if ( version_compare( $version, '0.4.0', '>=' ) ) {
		$recurring_id = wpse_lifecycle_probe_insert_recurring_event(
			$start->modify( '+1 day' ),
			$end->modify( '+1 day' ),
			$category_id,
			$tag_id
		);

		if ( $recurring_id <= 0 ) {
			return new WP_Error(
				'wpse_lifecycle_recurrence_failure',
				esc_html__( 'The recurring upgrade fixture could not be indexed.', 'mime-simple-events-calendar' ),
				array( 'status' => 500 )
			);
		}

		update_option( 'wpse_occurrence_index_migration_complete', true, false );
	}

	if ( version_compare( $version, '0.6.0', '>=' ) ) {
		update_term_meta( $category_id, '_wpse_category_color', '#336699' );
		update_post_meta( $event, '_wpse_color_mode', 'custom' );
		update_post_meta( $event, '_wpse_event_color', '#8844cc' );
	}

	$page_id = wpse_lifecycle_probe_insert_builder_page( $version, $event );

	if ( $page_id <= 0 ) {
		return new WP_Error(
			'wpse_lifecycle_builder_failure',
			esc_html__( 'The builder upgrade fixture could not be created.', 'mime-simple-events-calendar' ),
			array( 'status' => 500 )
		);
	}

	update_option( 'wpse_archive_slug', 'upgrade-events', false );
	update_option( 'wpse_archive_per_page', 7, false );
	update_option( 'wpse_archive_default_period', 'all', false );
	update_option( 'wpse_structured_data_enabled', false, false );
	update_option( 'wpse_show_event_timezone', true, false );
	update_option( 'wpse_delete_data_on_uninstall', false, false );

	if ( version_compare( $version, '0.7.0', '>=' ) ) {
		update_option( 'wpse_show_native_calendar_action', true, false );
	}

	return new WP_REST_Response(
		array(
			'event_id'     => $event,
			'recurring_id' => $recurring_id,
			'page_id'      => $page_id,
			'snapshot'     => wpse_lifecycle_probe_snapshot(),
		),
		201
	);
}

/**
 * Insert one canonical historical event.
 *
 * @param string            $slug        Stable event slug.
 * @param string            $title       Event title.
 * @param DateTimeImmutable $start       Canonical start.
 * @param DateTimeImmutable $end         Canonical end.
 * @param int               $category_id Assigned event category.
 * @param int               $tag_id      Assigned event tag.
 */
function wpse_lifecycle_probe_insert_event(
	string $slug,
	string $title,
	DateTimeImmutable $start,
	DateTimeImmutable $end,
	int $category_id,
	int $tag_id
): int|WP_Error {
	$event_id = wp_insert_post(
		array(
			'post_type'    => 'wpse_event',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => '<p>Canonical upgrade event content.</p><!-- wp:wpse/event-venue /-->',
			'post_excerpt' => 'Canonical upgrade event excerpt.',
		),
		true
	);

	if ( is_wp_error( $event_id ) ) {
		return $event_id;
	}

	update_post_meta( $event_id, WPSE_LIFECYCLE_PROBE_MARKER, '1' );
	wpse_lifecycle_probe_store_event_meta( $event_id, $start, $end );
	wp_set_object_terms( $event_id, array( $category_id ), 'wpse_event_category' );
	wp_set_object_terms( $event_id, array( $tag_id ), 'wpse_event_tag' );

	if ( class_exists( 'MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairer' ) ) {
		$repairer = new MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairer();
		$repairer->repair( $event_id, 'publish' );
	}

	return $event_id;
}

/**
 * Store the canonical metadata shared by every supported release.
 *
 * @param int               $event_id Canonical event post.
 * @param DateTimeImmutable $start    Canonical start.
 * @param DateTimeImmutable $end      Canonical end.
 */
function wpse_lifecycle_probe_store_event_meta(
	int $event_id,
	DateTimeImmutable $start,
	DateTimeImmutable $end
): void {
	$values = array(
		'_wpse_start_local'     => $start->format( 'Y-m-d\TH:i:s' ),
		'_wpse_end_local'       => $end->format( 'Y-m-d\TH:i:s' ),
		'_wpse_start_utc'       => $start->getTimestamp(),
		'_wpse_end_utc'         => $end->getTimestamp(),
		'_wpse_all_day'         => false,
		'_wpse_timezone'        => wp_timezone_string(),
		'_wpse_venue'           => 'Upgrade Hall',
		'_wpse_address'         => "Upgrade Street 1\nBrussels",
		'_wpse_location_url'    => 'https://example.com/upgrade-location',
		'_wpse_event_url'       => 'https://example.com/upgrade-registration',
		'_wpse_event_url_label' => 'Reserve an upgrade seat',
		'_wpse_event_status'    => 'postponed',
	);

	foreach ( $values as $key => $value ) {
		update_post_meta( $event_id, $key, $value );
	}
}

/**
 * Insert and project one canonical recurrence aggregate.
 *
 * @param DateTimeImmutable $start       Canonical first start.
 * @param DateTimeImmutable $end         Canonical first end.
 * @param int               $category_id Assigned event category.
 * @param int               $tag_id      Assigned event tag.
 */
function wpse_lifecycle_probe_insert_recurring_event(
	DateTimeImmutable $start,
	DateTimeImmutable $end,
	int $category_id,
	int $tag_id
): int {
	$event_id = wpse_lifecycle_probe_insert_event(
		'wpse-upgrade-recurring',
		'Upgrade recurring event',
		$start,
		$end,
		$category_id,
		$tag_id
	);

	if ( is_wp_error( $event_id ) ) {
		return 0;
	}

	$series_uid = '019c1d83-1798-4fac-a66d-ae8d67c46319';
	$aggregate  = array(
		'schema_version' => 1,
		'series_uid'     => $series_uid,
		'timezone'       => wp_timezone_string(),
		'segments'       => array(
			array(
				'id'         => 0,
				'anchor'     => $start->format( 'Y-m-d\TH:i:s' ),
				'template'   => array(
					'start_local' => $start->format( 'Y-m-d\TH:i:s' ),
					'end_local'   => $end->format( 'Y-m-d\TH:i:s' ),
					'all_day'     => false,
				),
				'definition' => array(
					'type'      => 'rule',
					'frequency' => 'weekly',
					'interval'  => 1,
					'end'       => array( 'mode' => 'never' ),
					'weekdays'  => array( (int) $start->format( 'N' ) ),
				),
			),
		),
		'manuals'        => array(),
		'exclusions'     => array(),
		'overrides'      => array(),
	);

	update_post_meta( $event_id, '_wpse_series_uid', $series_uid );
	update_post_meta( $event_id, '_wpse_recurrence_definition', wp_json_encode( $aggregate, JSON_UNESCAPED_SLASHES ) );

	$repairer = new MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairer();
	$status   = $repairer->repair( $event_id, 'publish' );

	return 'indexed' === $status->value ? $event_id : 0;
}

/**
 * Insert saved Gutenberg, Elementor and optional Divi identities.
 *
 * @param string $version  Historical plugin version.
 * @param int    $event_id Explicit event source.
 */
function wpse_lifecycle_probe_insert_builder_page( string $version, int $event_id ): int {
	$blocks = array(
		'<!-- wp:wpse/event-list {"period":"all"} /-->',
		'<!-- wp:wpse/event-calendar {"filters":true} /-->',
		'<!-- wp:wpse/event-details {"eventId":' . $event_id . '} /-->',
		'<!-- wp:wpse/event-title {"eventId":' . $event_id . '} /-->',
	);

	if ( version_compare( $version, '0.7.0', '>=' ) ) {
		$blocks[] = '<!-- wp:wpse/add-to-calendar {"eventId":' . $event_id . '} /-->';
	}

	if ( version_compare( $version, '0.5.0', '>=' ) ) {
		$blocks[] = '<!-- wp:mime-simple-events-calendar/event-calendar {"module":{"meta":{"adminLabel":"Upgrade Divi calendar"}}} /-->';
	}

	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => 'wpse-upgrade-builders',
			'post_title'   => 'Upgrade builder fixtures',
			'post_content' => implode( "\n", $blocks ),
		),
		true
	);

	if ( is_wp_error( $page_id ) ) {
		return 0;
	}

	$elementor = array(
		array(
			'id'         => 'wpse001',
			'elType'     => 'widget',
			'widgetType' => 'wpse-event-list',
			'settings'   => array( 'period' => 'all' ),
		),
		array(
			'id'         => 'wpse002',
			'elType'     => 'widget',
			'widgetType' => 'wpse-event-calendar',
			'settings'   => array( 'show_filters' => 'yes' ),
		),
	);

	if ( version_compare( $version, '0.7.0', '>=' ) ) {
		$elementor[] = array(
			'id'         => 'wpse003',
			'elType'     => 'widget',
			'widgetType' => 'wpse-add-to-calendar',
			'settings'   => array( 'event_id' => $event_id ),
		);
	}

	update_post_meta( $page_id, WPSE_LIFECYCLE_PROBE_MARKER, '1' );
	update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $page_id, '_elementor_data', wp_json_encode( $elementor, JSON_UNESCAPED_SLASHES ) );

	$attachment_id = wp_insert_attachment(
		array(
			'post_title'  => 'Shared upgrade media',
			'post_status' => 'inherit',
		)
	);
	update_post_meta( $attachment_id, WPSE_LIFECYCLE_PROBE_MARKER, '1' );
	update_post_meta( $page_id, '_thumbnail_id', $attachment_id );

	return $page_id;
}

/** Return the authenticated lifecycle snapshot. */
function wpse_lifecycle_probe_snapshot_response(): WP_REST_Response {
	return new WP_REST_Response( wpse_lifecycle_probe_snapshot() );
}

/** Build canonical and derived lifecycle evidence. */
function wpse_lifecycle_probe_snapshot(): array {
	global $wpdb;

	$canonical_meta = array(
		'_wpse_start_local',
		'_wpse_end_local',
		'_wpse_all_day',
		'_wpse_timezone',
		'_wpse_venue',
		'_wpse_address',
		'_wpse_location_url',
		'_wpse_event_url',
		'_wpse_event_url_label',
		'_wpse_event_status',
		'_wpse_recurrence_definition',
		'_wpse_series_uid',
		'_wpse_color_mode',
		'_wpse_event_color',
		'_wpse_display_category_id',
	);
	$events         = get_posts(
		array(
			'post_type'      => 'wpse_event',
			'post_status'    => array_keys( get_post_stati() ),
			'posts_per_page' => 100,
			'orderby'        => 'post_name',
			'order'          => 'ASC',
		)
	);
	$event_data     = array();

	foreach ( $events as $event ) {
		$meta = array();

		foreach ( $canonical_meta as $key ) {
			if ( metadata_exists( 'post', $event->ID, $key ) ) {
				$meta[ $key ] = get_post_meta( $event->ID, $key, true );
			}
		}

		$event_data[] = array(
			'id'         => $event->ID,
			'slug'       => $event->post_name,
			'status'     => $event->post_status,
			'title'      => $event->post_title,
			'content'    => $event->post_content,
			'excerpt'    => $event->post_excerpt,
			'meta'       => $meta,
			'categories' => wp_get_object_terms( $event->ID, 'wpse_event_category', array( 'fields' => 'slugs' ) ),
			'tags'       => wp_get_object_terms( $event->ID, 'wpse_event_tag', array( 'fields' => 'slugs' ) ),
		);
	}

	$pages     = get_posts(
		array(
			'post_type'      => array( 'page', 'attachment' ),
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'meta_key'       => WPSE_LIFECYCLE_PROBE_MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded test-only fixture lookup.
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded test-only fixture lookup.
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	$page_data = array();

	foreach ( $pages as $page ) {
		$page_data[] = array(
			'id'             => $page->ID,
			'type'           => $page->post_type,
			'slug'           => $page->post_name,
			'content'        => $page->post_content,
			'elementor_data' => get_post_meta( $page->ID, '_elementor_data', true ),
			'thumbnail_id'   => (int) get_post_meta( $page->ID, '_thumbnail_id', true ),
		);
	}

	$terms     = get_terms(
		array(
			'taxonomy'   => array( 'wpse_event_category', 'wpse_event_tag' ),
			'hide_empty' => false,
			'number'     => 100,
		)
	);
	$term_data = array();

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$term_data[] = array(
				'id'       => $term->term_id,
				'taxonomy' => $term->taxonomy,
				'slug'     => $term->slug,
				'color'    => get_term_meta( $term->term_id, '_wpse_category_color', true ),
			);
		}
	}

	usort( $term_data, static fn ( array $left, array $right ): int => ( $left['taxonomy'] . $left['slug'] ) <=> ( $right['taxonomy'] . $right['slug'] ) );

	$options = array();
	foreach ( wpse_lifecycle_probe_persistent_options() as $name ) {
		$value = get_option( $name, '__wpse_missing__' );

		if ( '__wpse_missing__' !== $value ) {
			$options[ $name ] = $value;
		}
	}

	$table_name  = $wpdb->prefix . 'wpse_event_occurrences';
	$table_query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );
	$table_found = is_string( $table_query ) ? $wpdb->get_var( $table_query ) : null; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Prepared test-only schema inspection.
	$columns     = array();
	$row_count   = 0;

	if ( $table_name === $table_found ) {
		$column_query = $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name );
		$count_query  = $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name );
		$column_rows  = is_string( $column_query ) ? $wpdb->get_results( $column_query ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Prepared test-only schema inspection.
		$row_count    = is_string( $count_query ) ? (int) $wpdb->get_var( $count_query ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Prepared test-only derived-row count.
		$columns      = array_map( static fn ( object $row ): string => (string) $row->Field, $column_rows ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL DESCRIBE exposes the fixed Field property.
	}

	return array(
		'plugin_version' => defined( 'WPSE_VERSION' ) ? WPSE_VERSION : null,
		'canonical'      => array(
			'events'  => $event_data,
			'pages'   => $page_data,
			'terms'   => $term_data,
			'options' => $options,
		),
		'derived'        => array(
			'schema_version'     => get_option( 'wpse_schema_version', null ),
			'migration_complete' => (bool) get_option( 'wpse_occurrence_index_migration_complete', false ),
			'table_exists'       => $table_name === $table_found,
			'table_columns'      => $columns,
			'occurrence_rows'    => $row_count,
			'scheduled'          => wpse_lifecycle_probe_scheduled_counts(),
			'renewal_offset'     => get_option( 'wpse_occurrence_projection_renewal_offset', null ),
			'pending_rewrite'    => get_option( 'wpse_pending_archive_rewrite_slug', null ),
		),
		'capabilities'   => wpse_lifecycle_probe_capabilities(),
	);
}

/** Return plugin-owned scheduled-hook counts. */
function wpse_lifecycle_probe_scheduled_counts(): array {
	$counts = array(
		'wpse_occurrence_index_migrate'      => 0,
		'wpse_occurrence_generation_cleanup' => 0,
		'wpse_occurrence_projection_renewal' => 0,
	);

	foreach ( (array) _get_cron_array() as $hooks ) {
		foreach ( array_keys( $counts ) as $hook ) {
			if ( isset( $hooks[ $hook ] ) && is_array( $hooks[ $hook ] ) ) {
				$counts[ $hook ] += count( $hooks[ $hook ] );
			}
		}
	}

	return $counts;
}

/** Return only the event capabilities granted to core editorial roles. */
function wpse_lifecycle_probe_capabilities(): array {
	$roles  = array( 'administrator', 'editor' );
	$result = array();

	foreach ( $roles as $role_name ) {
		$role = get_role( $role_name );
		$caps = array();

		if ( $role instanceof WP_Role ) {
			foreach ( $role->capabilities as $capability => $granted ) {
				if ( $granted && ( str_contains( $capability, 'wpse_event' ) || str_contains( $capability, 'wpse_events' ) ) ) {
					$caps[] = $capability;
				}
			}
		}

		sort( $caps );
		$result[ $role_name ] = $caps;
	}

	return $result;
}

/** Return persistent plugin options whose meaning must survive an upgrade. */
function wpse_lifecycle_probe_persistent_options(): array {
	return array(
		'wpse_archive_slug',
		'wpse_archive_per_page',
		'wpse_archive_default_period',
		'wpse_structured_data_enabled',
		'wpse_show_event_timezone',
		'wpse_show_native_calendar_action',
		'wpse_delete_data_on_uninstall',
	);
}

/** Execute the one-off migration like WordPress cron after removing its due job. */
function wpse_lifecycle_probe_run_migration(): WP_REST_Response {
	wp_clear_scheduled_hook( 'wpse_occurrence_index_migrate' );
	do_action( 'wpse_occurrence_index_migrate' );

	return new WP_REST_Response( wpse_lifecycle_probe_snapshot() );
}

/** Remove only the derived occurrence table to exercise automatic repair. */
function wpse_lifecycle_probe_drop_table(): WP_REST_Response {
	global $wpdb;

	$table_name = $wpdb->prefix . 'wpse_event_occurrences';
	$query      = $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name );

	if ( is_string( $query ) ) {
		$wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Deliberate test-only derived-table damage.
	}

	return new WP_REST_Response( wpse_lifecycle_probe_snapshot() );
}

/** Deactivate normally and capture the resulting retained state. */
function wpse_lifecycle_probe_deactivate(): WP_REST_Response {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	deactivate_plugins( WPSE_LIFECYCLE_PROBE_PLUGIN );

	if ( class_exists( '\\MiMe\\WPSimpleEvents\\Content\\ContentRegistry' ) ) {
		( new \MiMe\WPSimpleEvents\Content\ContentRegistry() )->register();
	}

	return new WP_REST_Response( wpse_lifecycle_probe_snapshot() );
}

/** Reactivate the canonical package. */
function wpse_lifecycle_probe_activate(): WP_REST_Response|WP_Error {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$result = activate_plugin( WPSE_LIFECYCLE_PROBE_PLUGIN );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( array( 'activated' => true ) );
}

/**
 * Invoke uninstall without a preceding deactivation, then silently remove the
 * active-plugin entry after capturing the uninstall result.
 *
 * @param WP_REST_Request $request Authenticated request.
 */
function wpse_lifecycle_probe_uninstall( WP_REST_Request $request ): WP_REST_Response {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$delete = rest_sanitize_boolean( $request->get_param( 'delete' ) );
	update_option( 'wpse_delete_data_on_uninstall', $delete, false );
	wp_schedule_single_event( time() + 300, 'wpse_occurrence_index_migrate' );
	wp_schedule_single_event( time() + 300, 'wpse_occurrence_generation_cleanup' );
	wp_schedule_single_event( time() + 300, 'wpse_occurrence_projection_renewal' );
	update_option( 'wpse_occurrence_projection_renewal_offset', 9, false );
	uninstall_plugin( WPSE_LIFECYCLE_PROBE_PLUGIN );
	$snapshot = wpse_lifecycle_probe_snapshot();
	deactivate_plugins( WPSE_LIFECYCLE_PROBE_PLUGIN, true );

	return new WP_REST_Response( $snapshot );
}

/** Remove test-only ordinary posts, media, terms and fixture bookkeeping. */
function wpse_lifecycle_probe_purge_fixtures(): WP_REST_Response {
	$posts = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => array_keys( get_post_stati() ),
			'posts_per_page' => 100,
			'meta_key'       => WPSE_LIFECYCLE_PROBE_MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded test-only fixture cleanup.
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded test-only fixture cleanup.
			'fields'         => 'ids',
		)
	);

	foreach ( $posts as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	$term_ids = get_option( 'wpse_lifecycle_probe_term_ids', array() );
	if ( is_array( $term_ids ) ) {
		wp_delete_term( (int) ( $term_ids[0] ?? 0 ), 'wpse_event_category' );
		wp_delete_term( (int) ( $term_ids[1] ?? 0 ), 'wpse_event_tag' );
	}
	delete_option( 'wpse_lifecycle_probe_term_ids' );

	return new WP_REST_Response( array( 'purged' => true ) );
}
