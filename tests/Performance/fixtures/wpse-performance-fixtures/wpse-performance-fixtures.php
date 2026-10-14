<?php
/**
 * Plugin Name: MiMe Simple Events and Calendar Performance Fixtures
 * Description: Deterministic test-only fixtures for the performance regression suite.
 * Version:     1.0.0
 * Author:      MiMe
 * License:     GPL-2.0-or-later
 *
 * @package MiMe\WPSimpleEvents\Tests\Performance
 */

declare(strict_types=1);

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Admin\EventMetaBox;
use MiMe\WPSimpleEvents\Domain\CalendarWindow;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceTable;
use MiMe\WPSimpleEvents\Occurrence\WordPressOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceCoverageMetaQuery;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionWindowPolicy;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;
use MiMe\WPSimpleEvents\Query\PublicEventOptions;
use MiMe\WPSimpleEvents\Recurrence\DeterministicRecurrenceEngine;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateJsonCodec;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WPSE_PERFORMANCE_PROGRESS_OPTION = 'wpse_performance_fixture_progress_v1';
const WPSE_PERFORMANCE_ONE_OFF_COUNT   = 400;
const WPSE_PERFORMANCE_RECURRING_COUNT = 100;
const WPSE_PERFORMANCE_ROWS_PER_SERIES = 50;
const WPSE_PERFORMANCE_EVENT_BATCH     = 50;
const WPSE_PERFORMANCE_ROW_BATCH       = 10;

/**
 * Return or create one deterministic taxonomy term.
 *
 * @param string $taxonomy Registered taxonomy name.
 * @param string $name     Human-readable fixture term name.
 * @param string $slug     Deterministic fixture term slug.
 */
function wpse_performance_term_id( string $taxonomy, string $name, string $slug ): int {
	$existing = term_exists( $slug, $taxonomy );

	if ( is_array( $existing ) ) {
		return (int) $existing['term_id'];
	}

	$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );

	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

/**
 * Create the complete deterministic taxonomy vocabulary.
 *
 * @return array{one_off: int, recurring: int, categories: int[], tags: int[]}
 */
function wpse_performance_terms(): array {
	$categories = array();
	$tags       = array();
	$one_off    = wpse_performance_term_id(
		'wpse_event_category',
		'Performance One-off',
		'wpse-perf-one-off'
	);
	$recurring  = wpse_performance_term_id(
		'wpse_event_category',
		'Performance Recurring',
		'wpse-perf-recurring'
	);

	for ( $index = 1; $index <= 18; ++$index ) {
		$categories[] = wpse_performance_term_id(
			'wpse_event_category',
			sprintf( 'Performance Category %02d', $index ),
			sprintf( 'wpse-perf-category-%02d', $index )
		);
	}

	for ( $index = 1; $index <= 40; ++$index ) {
		$tags[] = wpse_performance_term_id(
			'wpse_event_tag',
			sprintf( 'Performance Tag %02d', $index ),
			sprintf( 'wpse-perf-tag-%02d', $index )
		);
	}

	return array(
		'one_off'    => $one_off,
		'recurring'  => $recurring,
		'categories' => $categories,
		'tags'       => $tags,
	);
}

/**
 * Insert one public canonical event through the ordinary WordPress post APIs.
 *
 * @param int                                                                 $index Fixture event index.
 * @param array{one_off: int, recurring: int, categories: int[], tags: int[]} $terms Fixture terms.
 */
function wpse_performance_insert_event( int $index, array $terms ): int {
	$recurring = $index >= WPSE_PERFORMANCE_ONE_OFF_COUNT;
	$number    = $recurring ? $index - WPSE_PERFORMANCE_ONE_OFF_COUNT + 1 : $index + 1;
	$slug      = sprintf( 'wpse-perf-%s-%03d', $recurring ? 'recurring' : 'one-off', $number );
	$existing  = get_page_by_path( $slug, OBJECT, 'wpse_event' );

	if ( $existing instanceof WP_Post ) {
		return (int) $existing->ID;
	}

	$base                              = new DateTimeImmutable( $recurring ? '2035-01-01 09:00:00' : '2027-01-01 09:00:00', new DateTimeZone( 'UTC' ) );
	$start                             = $base->modify( '+' . ( $recurring ? $number % 7 : $index % 365 ) . ' days' );
	$end                               = $start->modify( '+2 hours' );
	$range                             = EventDateRange::from_local(
		$start->format( 'Y-m-d\TH:i:s' ),
		$end->format( 'Y-m-d\TH:i:s' ),
		false,
		'UTC'
	);
	$previous_post                     = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Fixture preserves the request before adding its own valid save nonce.
	$_POST[ EventMetaBox::NONCE_NAME ] = wp_create_nonce( EventMetaBox::NONCE_ACTION );
	$_POST['wpse_event']               = array(
		'start_date' => substr( $range->start_local(), 0, 10 ),
		'start_time' => substr( $range->start_local(), 11, 5 ),
		'end_date'   => substr( $range->end_local(), 0, 10 ),
		'end_time'   => substr( $range->end_local(), 11, 5 ),
		'all_day'    => '0',
		'status'     => 'scheduled',
	);

	try {
		$post = wp_insert_post(
			array(
				'post_type'    => 'wpse_event',
				'post_title'   => sprintf( 'Performance %s event %03d', $recurring ? 'recurring' : 'one-off', $number ),
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_excerpt' => 'Deterministic bounded performance fixture.',
				'meta_input'   => array(
					EventMeta::START_LOCAL => $range->start_local(),
					EventMeta::END_LOCAL   => $range->end_local(),
					EventMeta::START_UTC   => $range->start_utc(),
					EventMeta::END_UTC     => $range->end_utc(),
					EventMeta::ALL_DAY     => false,
					EventMeta::TIMEZONE    => 'UTC',
					EventMeta::STATUS      => 'scheduled',
				),
			),
			true
		);
	} finally {
		$_POST = $previous_post;
	}

	if ( is_wp_error( $post ) ) {
		return 0;
	}

	$category = $terms['categories'][ $index % count( $terms['categories'] ) ];
	$tag      = $terms['tags'][ $recurring ? $number % 10 : $index % count( $terms['tags'] ) ];
	wp_set_object_terms(
		$post,
		array( $recurring ? $terms['recurring'] : $terms['one_off'], $category ),
		'wpse_event_category'
	);
	wp_set_object_terms( $post, array( $tag ), 'wpse_event_tag' );

	return (int) $post;
}

/**
 * Replace one synthetic recurring series with fifty valid active projection rows.
 *
 * @param int $series_index Zero-based recurring-series index.
 */
function wpse_performance_insert_occurrences( int $series_index ): bool {
	global $wpdb;

	if ( ! $wpdb instanceof wpdb ) {
		return false;
	}

	$slug  = sprintf( 'wpse-perf-recurring-%03d', $series_index + 1 );
	$event = get_page_by_path( $slug, OBJECT, 'wpse_event' );

	if ( ! $event instanceof WP_Post ) {
		return false;
	}

	$table      = ( new OccurrenceTable() )->table_name();
	$generation = 2;
	$created    = time();
	$base       = new DateTimeImmutable( '2035-01-01 09:00:00', new DateTimeZone( 'UTC' ) );

	$wpdb->delete( $table, array( 'event_id' => $event->ID ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Disposable fixture owns derived test rows and is never shipped.

	for ( $row = 0; $row < WPSE_PERFORMANCE_ROWS_PER_SERIES; ++$row ) {
		$start         = $base->modify( '+' . ( ( $row * 7 ) + ( $series_index % 7 ) ) . ' days' );
		$end           = $start->modify( '+2 hours' );
		$recurrence_id = $start->format( 'Y-m-d\TH:i:s' );
		$inserted      = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Disposable fixture owns derived test rows and is never shipped.
			$table,
			array(
				'event_id'      => $event->ID,
				'public_key'    => md5( 'wpse-performance-' . $event->ID . '-' . $row ),
				'recurrence_id' => $recurrence_id,
				'generation'    => $generation,
				'created_utc'   => $created,
				'segment_id'    => 1,
				'source'        => 'rule',
				'start_local'   => $recurrence_id,
				'end_local'     => $end->format( 'Y-m-d\TH:i:s' ),
				'start_utc'     => $start->getTimestamp(),
				'end_utc'       => $end->getTimestamp(),
				'timezone'      => 'UTC',
				'all_day'       => 0,
				'event_status'  => 'scheduled',
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}
	}

	update_post_meta( $event->ID, EventMeta::ACTIVE_GENERATION, $generation );
	update_post_meta( $event->ID, EventMeta::COVERAGE_FROM, '2026-01-01' );
	update_post_meta( $event->ID, EventMeta::COVERAGE_THROUGH, '2036-12-31' );
	update_post_meta( $event->ID, EventMeta::COVERAGE_GENERATION, $generation );
	delete_post_meta( $event->ID, EventMeta::INDEX_DIRTY );

	return true;
}

/**
 * Advance one bounded seed batch and report completion.
 *
 * @return array{complete: bool, phase: string, current: int, total: int}
 */
function wpse_performance_seed_batch(): array {
	$progress = get_option(
		WPSE_PERFORMANCE_PROGRESS_OPTION,
		array(
			'phase'   => 'events',
			'current' => 0,
		)
	);
	$phase    = is_array( $progress ) && isset( $progress['phase'] ) ? (string) $progress['phase'] : 'events';
	$current  = is_array( $progress ) && isset( $progress['current'] ) ? max( 0, (int) $progress['current'] ) : 0;

	if ( 'events' === $phase ) {
		$terms = wpse_performance_terms();
		$end   = min( WPSE_PERFORMANCE_ONE_OFF_COUNT + WPSE_PERFORMANCE_RECURRING_COUNT, $current + WPSE_PERFORMANCE_EVENT_BATCH );

		for ( $index = $current; $index < $end; ++$index ) {
			if ( 0 === wpse_performance_insert_event( $index, $terms ) ) {
				return array(
					'complete' => false,
					'phase'    => 'error',
					'current'  => $index,
					'total'    => 500,
				);
			}
		}

		if ( $end < WPSE_PERFORMANCE_ONE_OFF_COUNT + WPSE_PERFORMANCE_RECURRING_COUNT ) {
			update_option(
				WPSE_PERFORMANCE_PROGRESS_OPTION,
				array(
					'phase'   => 'events',
					'current' => $end,
				),
				false
			);

			return array(
				'complete' => false,
				'phase'    => 'events',
				'current'  => $end,
				'total'    => 500,
			);
		}

		update_option(
			WPSE_PERFORMANCE_PROGRESS_OPTION,
			array(
				'phase'   => 'occurrences',
				'current' => 0,
			),
			false
		);

		return array(
			'complete' => false,
			'phase'    => 'occurrences',
			'current'  => 0,
			'total'    => 100,
		);
	}

	if ( 'occurrences' === $phase ) {
		$end = min( WPSE_PERFORMANCE_RECURRING_COUNT, $current + WPSE_PERFORMANCE_ROW_BATCH );

		for ( $index = $current; $index < $end; ++$index ) {
			if ( ! wpse_performance_insert_occurrences( $index ) ) {
				return array(
					'complete' => false,
					'phase'    => 'error',
					'current'  => $index,
					'total'    => 100,
				);
			}
		}

		if ( $end < WPSE_PERFORMANCE_RECURRING_COUNT ) {
			update_option(
				WPSE_PERFORMANCE_PROGRESS_OPTION,
				array(
					'phase'   => 'occurrences',
					'current' => $end,
				),
				false
			);

			return array(
				'complete' => false,
				'phase'    => 'occurrences',
				'current'  => $end,
				'total'    => 100,
			);
		}

		update_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, true, false );
		wp_update_term_count_now(
			get_terms(
				array(
					'taxonomy'   => 'wpse_event_category',
					'fields'     => 'ids',
					'hide_empty' => false,
				)
			),
			'wpse_event_category'
		);
		wp_update_term_count_now(
			get_terms(
				array(
					'taxonomy'   => 'wpse_event_tag',
					'fields'     => 'ids',
					'hide_empty' => false,
				)
			),
			'wpse_event_tag'
		);
		update_option(
			WPSE_PERFORMANCE_PROGRESS_OPTION,
			array(
				'phase'   => 'complete',
				'current' => 500,
			),
			false
		);

		return array_merge(
			array(
				'complete' => true,
				'phase'    => 'complete',
				'current'  => 500,
				'total'    => 500,
			),
			wpse_performance_fixture_health()
		);
	}

	$result = array(
		'complete' => 'complete' === $phase,
		'phase'    => $phase,
		'current'  => $current,
		'total'    => 500,
	);

	return 'complete' === $phase
		? array_merge( $result, wpse_performance_fixture_health() )
		: $result;
}

/**
 * Report deterministic fixture health before any timed scenario runs.
 *
 * @return array<string, int|string>
 */
function wpse_performance_fixture_health(): array {
	global $wpdb;

	if ( ! $wpdb instanceof wpdb ) {
		return array( 'health_error' => 'WordPress database unavailable.' );
	}

	$table       = ( new OccurrenceTable() )->table_name();
	$count_query = $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table );
	$rule_query  = $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source = %s', $table, 'rule' );
	$event       = get_page_by_path( 'wpse-perf-recurring-001', OBJECT, 'wpse_event' );

	if ( ! is_string( $count_query ) || ! is_string( $rule_query ) || ! $event instanceof WP_Post ) {
		return array( 'health_error' => 'Fixture table or recurring event unavailable.' );
	}

	try {
		$repository      = new OccurrenceReadRepository();
		$window          = new CalendarWindow( '2035-01-01', '2036-01-01' );
		$unfiltered_page = $repository->query_window( new EventWindowCriteria( $window, 1, 1, array(), array() ) );
		$category_page   = $repository->query_window(
			new EventWindowCriteria( $window, 1, 1, array( 'wpse-perf-recurring' ), array() )
		);
	} catch ( Throwable $error ) {
		return array( 'health_error' => $error->getMessage() );
	}

	return array(
		'occurrence_rows'        => (int) $wpdb->get_var( $count_query ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Test-only health query was prepared immediately above.
		'recurring_rows'         => (int) $wpdb->get_var( $rule_query ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Test-only health query was prepared immediately above.
		'sample_event_status'    => $event->post_status,
		'sample_generation'      => (int) get_post_meta( $event->ID, EventMeta::ACTIVE_GENERATION, true ),
		'sample_coverage'        => (int) get_post_meta( $event->ID, EventMeta::COVERAGE_GENERATION, true ),
		'sample_index_dirty'     => metadata_exists( 'post', $event->ID, EventMeta::INDEX_DIRTY ) ? 1 : 0,
		'sample_category_count'  => count( wp_get_object_terms( $event->ID, 'wpse_event_category' ) ),
		'sample_occurrence_rows' => (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test-only diagnostic for an internally selected fixture event.
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_id = %d', $table, $event->ID )
		),
		'unfiltered_public_rows' => $unfiltered_page->total,
		'category_public_rows'   => $category_page->total,
	);
}

/**
 * Measure one public runtime scenario after WordPress has booted.
 *
 * @param string $scenario Allowlisted scenario identifier.
 * @return array<string, int|float|string>
 */
function wpse_performance_measure( string $scenario ): array {
	global $wpdb;

	if ( ! $wpdb instanceof wpdb ) {
		return array( 'error' => 'WordPress database unavailable.' );
	}

	wp_cache_flush();
	$queries = $wpdb->num_queries;
	$start   = hrtime( true );
	$result  = match ( $scenario ) {
		'coverage_probe'             => array(
			'count' => (int) ( new WordPressOccurrenceCoverageProbe() )->has_public_gap(),
			'total' => 0,
			'bytes' => 0,
		),
		'occurrence_window_filtered' => wpse_performance_occurrence_window(),
		'event_list'                 => wpse_performance_event_list(),
		'calendar_feed'              => wpse_performance_calendar_feed(),
		'builder_options'            => wpse_performance_builder_options(),
		'recurrence_engine'          => wpse_performance_recurrence_engine(),
		default                      => array( 'error' => 'Unknown scenario.' ),
	};

	$result['scenario']    = $scenario;
	$result['duration_ms'] = round( ( hrtime( true ) - $start ) / 1_000_000, 3 );
	$result['queries']     = $wpdb->num_queries - $queries;

	return $result;
}

/**
 * Measure one filtered occurrence repository window.
 *
 * @return array{count: int, total: int, bytes: int}
 */
function wpse_performance_occurrence_window(): array {
	$page = ( new OccurrenceReadRepository() )->query_window(
		new EventWindowCriteria(
			new CalendarWindow( '2035-01-01', '2036-01-01' ),
			100,
			1,
			array( 'wpse-perf-recurring' ),
			array()
		)
	);

	return array(
		'count' => count( $page->occurrences ),
		'total' => $page->total,
		'bytes' => 0,
	);
}

/**
 * Measure the complete public event-list and filter renderer.
 *
 * @return array{count: int, total: int, bytes: int}
 */
function wpse_performance_event_list(): array {
	$html = do_shortcode( '[wpse_events category="wpse-perf-one-off" period="all" limit="50" filters="true" pagination="true"]' );

	return array(
		'count' => substr_count( $html, '<article class="wpse-event-card' ),
		'total' => 400,
		'bytes' => strlen( $html ),
	);
}

/**
 * Measure one maximum-size public calendar REST page.
 *
 * @return array{count: int, total: int, bytes: int}
 */
function wpse_performance_calendar_feed(): array {
	$request = new WP_REST_Request( 'GET', '/wpse/v1/events' );
	$request->set_query_params(
		array(
			'start'      => '2027-01-01T00:00:00+00:00',
			'end'        => '2028-01-01T00:00:00+00:00',
			'categories' => 'wpse-perf-one-off',
			'per_page'   => 100,
			'page'       => 1,
		)
	);
	$response = rest_do_request( $request );
	$data     = $response->get_data();

	return array(
		'count' => is_array( $data ) ? count( $data ) : 0,
		'total' => (int) $response->get_headers()['X-WP-Total'],
		'bytes' => strlen( (string) wp_json_encode( $data ) ),
	);
}

/**
 * Measure repeated reads from the shared visual-builder event options provider.
 *
 * @return array{count: int, total: int, bytes: int}
 */
function wpse_performance_builder_options(): array {
	$provider = new PublicEventOptions();
	$options  = array();

	for ( $index = 0; $index < 16; ++$index ) {
		$options = $provider->options();
	}

	return array(
		'count' => count( $options ),
		'total' => count( $options ),
		'bytes' => 0,
	);
}

/**
 * Measure the largest supported daily recurrence generation horizon.
 *
 * @return array{count: int, total: int, bytes: int}
 */
function wpse_performance_recurrence_engine(): array {
	$template = EventDateRange::from_local( '2026-09-01T09:00:00', '2026-09-01T10:00:00', false, 'UTC' );
	$result   = ( new DeterministicRecurrenceEngine() )->generate(
		$template,
		RecurrenceRule::daily(),
		RecurrenceGenerationWindow::between( '2026-09-01', '2028-03-04', 1_000 )
	);

	return array(
		'count' => count( $result->slots() ),
		'total' => count( $result->slots() ),
		'bytes' => 0,
	);
}

/** Handle the bounded test-only seed request. */
function wpse_performance_seed_ajax(): void {
	// The disposable endpoint runs without a browser login, but event publication
	// must exercise the same capability and nonce boundary as an administrator.
	// This fixture is excluded from every release package.
	wp_set_current_user( 1 );
	wp_send_json( wpse_performance_seed_batch(), 200 );
}

/**
 * Verify coverage predicates against real WordPress metadata before benchmarking.
 *
 * The temporary fixture is deleted even if a case fails. This code never ships.
 *
 * @return array<string, array{expected: bool, combined: bool, split: bool}>
 * @throws RuntimeException If the isolated fixture cannot be created.
 */
function wpse_performance_coverage_contract(): array {
	wp_set_current_user( 1 );
	$event_id = wpse_performance_insert_event( 1500, wpse_performance_terms() );

	if ( $event_id <= 0 ) {
		throw new RuntimeException( 'Could not create coverage contract fixture.' );
	}

	$root       = EventDateRange::from_local(
		get_post_meta( $event_id, EventMeta::START_LOCAL, true ),
		get_post_meta( $event_id, EventMeta::END_LOCAL, true ),
		false,
		'UTC'
	);
	$definition = ( new RecurrenceAggregateJsonCodec() )->encode(
		RecurrenceAggregate::create(
			get_post_meta( $event_id, EventMeta::SERIES_UID, true ),
			'UTC',
			array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily() ) )
		)
	);
	$today      = wp_date( 'Y-m-d' );
	$through    = ( new OccurrenceProjectionWindowPolicy() )->minimum_through( $today );
	$base       = array(
		EventMeta::START_LOCAL         => $root->start_local(),
		EventMeta::RECURRENCE          => $definition,
		EventMeta::ACTIVE_GENERATION   => 1,
		EventMeta::INDEX_DIRTY         => null,
		EventMeta::COVERAGE_FROM       => $today,
		EventMeta::COVERAGE_THROUGH    => $through,
		EventMeta::COVERAGE_GENERATION => 1,
	);
	$cases      = array(
		'healthy_recurring'        => array( false, array() ),
		'missing_active'           => array( true, array( EventMeta::ACTIVE_GENERATION => null ) ),
		'dirty'                    => array( true, array( EventMeta::INDEX_DIRTY => '1' ) ),
		'missing_from'             => array( true, array( EventMeta::COVERAGE_FROM => null ) ),
		'future_from'              => array( true, array( EventMeta::COVERAGE_FROM => '2099-01-01' ) ),
		'missing_through'          => array( true, array( EventMeta::COVERAGE_THROUGH => null ) ),
		'expired_through'          => array( true, array( EventMeta::COVERAGE_THROUGH => '2000-01-01' ) ),
		'missing_generation'       => array( true, array( EventMeta::COVERAGE_GENERATION => null ) ),
		'zero_generation'          => array( true, array( EventMeta::COVERAGE_GENERATION => 0 ) ),
		'negative_generation'      => array( true, array( EventMeta::COVERAGE_GENERATION => -1 ) ),
		'one_off_without_coverage' => array(
			false,
			array(
				EventMeta::RECURRENCE          => null,
				EventMeta::COVERAGE_FROM       => null,
				EventMeta::COVERAGE_THROUGH    => null,
				EventMeta::COVERAGE_GENERATION => null,
			),
		),
		'one_off_missing_active'   => array(
			true,
			array(
				EventMeta::RECURRENCE        => null,
				EventMeta::ACTIVE_GENERATION => null,
			),
		),
		'empty_recurrence'         => array(
			false,
			array(
				EventMeta::RECURRENCE          => '',
				EventMeta::COVERAGE_GENERATION => null,
			),
		),
		'empty_start'              => array(
			false,
			array(
				EventMeta::START_LOCAL => '',
				EventMeta::INDEX_DIRTY => '1',
			),
		),
		'draft_gap'                => array( false, array( EventMeta::ACTIVE_GENERATION => null ), 'draft' ),
		'private_gap'              => array( false, array( EventMeta::ACTIVE_GENERATION => null ), 'private' ),
		'protected_gap'            => array( false, array( EventMeta::ACTIVE_GENERATION => null ), 'publish', 'fixture-password' ),
	);
	$results    = array();

	try {
		foreach ( $cases as $name => $case ) {
			// Restore canonical metadata before changing visibility through WordPress.
			delete_post_meta( $event_id, EventMeta::RECURRENCE );
			update_post_meta( $event_id, EventMeta::START_LOCAL, $root->start_local() );
			wp_update_post(
				array(
					'ID'            => $event_id,
					'post_status'   => $case[2] ?? 'publish',
					'post_password' => $case[3] ?? '',
				)
			);

			$fixture_post = get_post( $event_id );
			if ( ! $fixture_post instanceof WP_Post || ( $case[2] ?? 'publish' ) !== $fixture_post->post_status || ( $case[3] ?? '' ) !== $fixture_post->post_password ) {
				throw new RuntimeException( 'Coverage fixture visibility did not match its case.' );
			}

			foreach ( array_replace( $base, $case[1] ) as $key => $value ) {
				if ( null === $value ) {
					delete_post_meta( $event_id, $key );
				} else {
					update_post_meta( $event_id, $key, $value );
				}
			}

			$combined         = get_posts(
				array(
					'post_type'      => 'wpse_event',
					'post_status'    => 'publish',
					'has_password'   => false,
					'post__in'       => array( $event_id ),
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'meta_query'     => ( new OccurrenceCoverageMetaQuery() )->public_gaps( $today ),
				)
			);
			$results[ $name ] = array(
				'expected' => $case[0],
				'combined' => array() !== $combined,
				'split'    => ( new WordPressOccurrenceCoverageProbe() )->has_public_gap(),
			);
		}
	} finally {
		wp_delete_post( $event_id, true );
	}

	return $results;
}

/** Handle one test-only measured scenario. */
function wpse_performance_measure_ajax(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, isolated fixture endpoint excluded from release packages.
	$scenario = isset( $_GET['scenario'] ) ? sanitize_key( wp_unslash( $_GET['scenario'] ) ) : '';
	wp_send_json( 'coverage_contract' === $scenario ? wpse_performance_coverage_contract() : wpse_performance_measure( $scenario ), 200 );
}

add_action( 'wp_ajax_nopriv_wpse_performance_seed', 'wpse_performance_seed_ajax' );
add_action( 'wp_ajax_wpse_performance_seed', 'wpse_performance_seed_ajax' );
add_action( 'wp_ajax_nopriv_wpse_performance_measure', 'wpse_performance_measure_ajax' );
add_action( 'wp_ajax_wpse_performance_measure', 'wpse_performance_measure_ajax' );
