<?php
/**
 * Administrator-only occurrence parity probe for the isolated smoke theme.
 *
 * @package MiMe\WPSimpleEvents\Tests\Fixtures
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Fixtures;

use DateTimeImmutable;
use DateTimeZone;
use MiMe\WPSimpleEvents\Application\RecurrenceSaveCoordinator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\CalendarWindow;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionRenewalBatchProcessor;
use MiMe\WPSimpleEvents\Occurrence\WordPressOccurrenceGenerationCleaner;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Query\EventRepository;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;
use MiMe\WPSimpleEvents\Recurrence\ManualOccurrence;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Compares both repositories inside a real WordPress database request.
 */
final class SmokeOccurrenceProbe {
	/**
	 * Register the administrator-only probe route.
	 */
	public static function register(): void {
		register_rest_route(
			'wpse-smoke/v1',
			'/occurrence-parity',
			array(
				'methods'             => 'GET',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
				'callback'            => array( self::class, 'probe' ),
				'args'                => array(
					'event_id'           => self::positive_id_argument(),
					'protected_event_id' => self::positive_id_argument(),
					'draft_event_id'     => self::positive_id_argument(),
					'window_start'       => self::date_argument(),
					'window_end'         => self::date_argument(),
				),
			)
		);

		register_rest_route(
			'wpse-smoke/v1',
			'/recurrence-projection',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
				'callback'            => array( self::class, 'project_recurrence' ),
				'args'                => array( 'event_id' => self::positive_id_argument() ),
			)
		);

		register_rest_route(
			'wpse-smoke/v1',
			'/recurrence-health',
			array(
				'methods'             => 'GET',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
				'callback'            => array( self::class, 'recurrence_health' ),
				'args'                => array( 'event_id' => self::positive_id_argument() ),
			)
		);

		register_rest_route(
			'wpse-smoke/v1',
			'/recurrence-repair-needed',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
				'callback'            => array( self::class, 'mark_recurrence_repair_needed' ),
				'args'                => array( 'event_id' => self::positive_id_argument() ),
			)
		);

		register_rest_route(
			'wpse-smoke/v1',
			'/generation-cleanup',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
				'callback'            => array( self::class, 'generation_cleanup' ),
				'args'                => array( 'event_id' => self::positive_id_argument() ),
			)
		);

		register_rest_route(
			'wpse-smoke/v1',
			'/recurrence-renewal',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
				'callback'            => array( self::class, 'renew_recurrence' ),
				'args'                => array( 'event_id' => self::positive_id_argument() ),
			)
		);
	}

	/**
	 * Narrow one smoke projection so the protected manual-repair journey remains testable.
	 *
	 * @param WP_REST_Request $request Validated administrator request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function mark_recurrence_repair_needed( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$event_id   = (int) $request['event_id'];
		$today      = wp_date( 'Y-m-d' );
		$generation = (int) get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true );

		if ( EventPostType::POST_TYPE !== get_post_type( $event_id )
			|| ! is_string( $today )
			|| $generation <= 0
		) {
			return new WP_Error( 'wpse_smoke_invalid_event', __( 'The smoke event is unavailable.', 'mime-simple-events-calendar' ), array( 'status' => 400 ) );
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $today, new DateTimeZone( 'UTC' ) );

		if ( false === $date ) {
			return new WP_Error( 'wpse_smoke_invalid_date', __( 'The smoke repair date is unavailable.', 'mime-simple-events-calendar' ), array( 'status' => 500 ) );
		}

		update_post_meta( $event_id, EventMeta::COVERAGE_FROM, $today );
		update_post_meta( $event_id, EventMeta::COVERAGE_THROUGH, $date->modify( '+10 days' )->format( 'Y-m-d' ) );
		update_post_meta( $event_id, EventMeta::COVERAGE_GENERATION, $generation );
		delete_post_meta( $event_id, EventMeta::INDEX_DIRTY );

		return new WP_REST_Response( array( 'marked' => true ) );
	}

	/**
	 * Prove buffered renewal while the public minimum remains available.
	 *
	 * @param WP_REST_Request $request Validated administrator request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function renew_recurrence( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$event_id = (int) $request['event_id'];
		$today    = wp_date( 'Y-m-d' );

		if ( EventPostType::POST_TYPE !== get_post_type( $event_id ) || ! is_string( $today ) ) {
			return new WP_Error( 'wpse_smoke_invalid_event', __( 'The smoke event is unavailable.', 'mime-simple-events-calendar' ), array( 'status' => 400 ) );
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $today, new DateTimeZone( 'UTC' ) );

		if ( false === $date ) {
			return new WP_Error( 'wpse_smoke_invalid_date', __( 'The smoke renewal date is unavailable.', 'mime-simple-events-calendar' ), array( 'status' => 500 ) );
		}

		update_post_meta( $event_id, EventMeta::COVERAGE_FROM, $today );
		update_post_meta( $event_id, EventMeta::COVERAGE_THROUGH, $date->modify( '+400 days' )->format( 'Y-m-d' ) );

		$ready_before = ( new OccurrenceReadiness() )->ready();
		$result       = ( new OccurrenceProjectionRenewalBatchProcessor() )->process();

		return new WP_REST_Response(
			array(
				'ready_before'        => $ready_before,
				'processed'           => $result->processed,
				'indexed'             => $result->indexed,
				'invalid'             => $result->skipped_invalid,
				'failed'              => $result->failed,
				'coverage_from'       => (string) get_post_meta( $event_id, EventMeta::COVERAGE_FROM, true ),
				'coverage_through'    => (string) get_post_meta( $event_id, EventMeta::COVERAGE_THROUGH, true ),
				'coverage_generation' => (int) get_post_meta( $event_id, EventMeta::COVERAGE_GENERATION, true ),
			)
		);
	}

	/**
	 * Prove old inactive cleanup against the real projection table.
	 *
	 * @param WP_REST_Request $request Validated administrator request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function generation_cleanup( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$event_id  = (int) $request['event_id'];
		$active    = (int) get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true );
		$dirty_key = EventMeta::INDEX_DIRTY;

		global $wpdb;

		if ( ! $wpdb instanceof \wpdb || $active <= 0 ) {
			return new WP_Error( 'wpse_smoke_no_projection', __( 'The smoke projection is unavailable.', 'mime-simple-events-calendar' ), array( 'status' => 500 ) );
		}

		$table = $wpdb->prefix . 'wpse_event_occurrences';
		$aged  = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Isolated smoke fixture deliberately ages only its own projection rows.
			$wpdb->prepare(
				'UPDATE %i SET created_utc = 0 WHERE event_id = %d',
				$table,
				$event_id
			)
		);

		if ( false === $aged ) {
			return new WP_Error( 'wpse_smoke_cleanup_setup_failed', __( 'The smoke cleanup fixture could not be aged.', 'mime-simple-events-calendar' ), array( 'status' => 500 ) );
		}

		$inactive_count  = static function () use ( $wpdb, $table, $event_id, $active ): int {
			$sql = $wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE event_id = %d AND generation <> %d',
				$table,
				$event_id,
				$active
			);

			return is_string( $sql ) ? (int) $wpdb->get_var( $sql ) : -1; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Isolated fixture reads only its own immediately prepared projection rows.
		};
		$active_count    = static function () use ( $wpdb, $table, $event_id, $active ): int {
			$sql = $wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE event_id = %d AND generation = %d',
				$table,
				$event_id,
				$active
			);

			return is_string( $sql ) ? (int) $wpdb->get_var( $sql ) : -1; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Isolated fixture reads only its own immediately prepared projection rows.
		};
		$inactive_before = $inactive_count();
		$cleaner         = new WordPressOccurrenceGenerationCleaner();

		update_post_meta( $event_id, $dirty_key, true );
		$dirty_removed  = $cleaner->clean_before( time() - 86400, 100 );
		$dirty_retained = $inactive_count();
		delete_post_meta( $event_id, $dirty_key );

		$clean_removed  = $cleaner->clean_before( time() - 86400, 100 );
		$inactive_after = $inactive_count();
		$active_after   = $active_count();

		return new WP_REST_Response(
			array(
				'inactive_before' => $inactive_before,
				'dirty_removed'   => $dirty_removed,
				'dirty_retained'  => $dirty_retained,
				'clean_removed'   => $clean_removed,
				'inactive_after'  => $inactive_after,
				'active_after'    => $active_after,
			)
		);
	}

	/**
	 * Return bounded active-projection health after an ordinary recurring save.
	 *
	 * @param WP_REST_Request $request Validated administrator request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function recurrence_health( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$event_id = (int) $request['event_id'];

		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return new WP_Error( 'wpse_smoke_no_database', __( 'The smoke probe database is unavailable.', 'mime-simple-events-calendar' ), array( 'status' => 500 ) );
		}

		$generation = (int) get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true );
		$table      = $wpdb->prefix . 'wpse_event_occurrences';
		$sql        = $wpdb->prepare(
			'SELECT public_key FROM %i WHERE event_id = %d AND generation = %d ORDER BY start_utc ASC, public_key ASC LIMIT 10',
			$table,
			$event_id,
			$generation
		);
		$keys       = is_string( $sql ) ? $wpdb->get_col( $sql ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Isolated fixture querying the plugin-owned projection with immediately prepared SQL.
		$exact      = null;

		if ( is_array( $keys ) && isset( $keys[0] ) && is_string( $keys[0] ) ) {
			try {
				$exact = ( new OccurrenceReadRepository() )->find_public( $event_id, $keys[0] );
			} catch ( \Throwable ) {
				$exact = null;
			}
		}

		return new WP_REST_Response(
			array(
				'generation'          => $generation,
				'dirty'               => metadata_exists( 'post', $event_id, EventMeta::INDEX_DIRTY ),
				'coverage_from'       => (string) get_post_meta( $event_id, EventMeta::COVERAGE_FROM, true ),
				'coverage_through'    => (string) get_post_meta( $event_id, EventMeta::COVERAGE_THROUGH, true ),
				'coverage_generation' => (int) get_post_meta( $event_id, EventMeta::COVERAGE_GENERATION, true ),
				'row_count'           => is_array( $keys ) ? count( $keys ) : 0,
				'first_public_key'    => is_array( $keys ) && isset( $keys[0] ) && is_string( $keys[0] ) ? $keys[0] : '',
				'exact_found'         => $exact instanceof OccurrenceReadModel,
				'aggregate_loaded'    => ( new WordPressRecurrenceAggregateStore() )->load( $event_id ) instanceof RecurrenceAggregate,
			)
		);
	}

	/**
	 * Save and project one deterministic recurring draft through production adapters.
	 *
	 * @param WP_REST_Request $request Validated administrator request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function project_recurrence( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$event_id = (int) $request['event_id'];
		$uid      = get_post_meta( $event_id, EventMeta::SERIES_UID, true );
		$timezone = get_post_meta( $event_id, EventMeta::TIMEZONE, true );
		$start    = get_post_meta( $event_id, EventMeta::START_LOCAL, true );
		$end      = get_post_meta( $event_id, EventMeta::END_LOCAL, true );

		if ( EventPostType::POST_TYPE !== get_post_type( $event_id )
			|| ! is_string( $uid )
			|| ! is_string( $timezone )
			|| ! is_string( $start )
			|| ! is_string( $end )
		) {
			return new WP_Error( 'wpse_smoke_invalid_recurrence_event', __( 'The recurrence smoke event is incomplete.', 'mime-simple-events-calendar' ), array( 'status' => 400 ) );
		}

		try {
			$root        = EventDateRange::from_local( $start, $end, false, $timezone );
			$zone        = new DateTimeZone( $timezone );
			$seed        = new DateTimeImmutable( $root->start_local(), $zone );
			$identity    = static fn ( int $days ): string => $seed->modify( '+' . $days . ' days' )->format( 'Y-m-d\TH:i:s' );
			$moved_date  = $seed->modify( '+2 days' )->format( 'Y-m-d' );
			$manual_date = $seed->modify( '+3 days' )->format( 'Y-m-d' );
			$moved       = EventDateRange::from_local( $moved_date . 'T12:00:00', $moved_date . 'T14:00:00', false, $timezone );
			$manual      = EventDateRange::from_local( $manual_date . 'T16:00:00', $manual_date . 'T17:00:00', false, $timezone );
			$aggregate   = RecurrenceAggregate::create(
				$uid,
				$timezone,
				array( new ScheduleSegment( 0, $root->start_local(), $root, RecurrenceRule::daily() ) ),
				array( new ManualOccurrence( 'manual:019c1d83-1798-4fac-a66d-ae8d67c46320', $manual ) ),
				array(
					new OccurrenceExclusion( $identity( 1 ), OccurrenceExclusionAction::SKIP ),
					new OccurrenceExclusion( $identity( 4 ), OccurrenceExclusionAction::CANCEL ),
				),
				array(
					OccurrenceOverride::from_fields(
						$identity( 2 ),
						array(
							OccurrenceOverride::DATE_RANGE => $moved,
							OccurrenceOverride::STATUS     => EventStatus::POSTPONED,
						)
					),
				)
			);
			$window      = RecurrenceGenerationWindow::between(
				$seed->format( 'Y-m-d' ),
				$seed->modify( '+4 days' )->format( 'Y-m-d' ),
				20
			);
			$coordinator = new RecurrenceSaveCoordinator();
			$first       = $coordinator->save( $event_id, $aggregate, $window );
			$generation  = (int) get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true );
			$second      = $coordinator->save( $event_id, $aggregate, $window );
		} catch ( \Throwable ) {
			return new WP_Error( 'wpse_smoke_recurrence_failed', __( 'The recurrence smoke projection failed.', 'mime-simple-events-calendar' ), array( 'status' => 500 ) );
		}

		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return new WP_Error( 'wpse_smoke_no_database', __( 'The smoke probe database is unavailable.', 'mime-simple-events-calendar' ), array( 'status' => 500 ) );
		}

		$table  = $wpdb->prefix . 'wpse_event_occurrences';
		$sql    = $wpdb->prepare(
			'SELECT public_key, recurrence_id, source, event_status, start_local FROM %i WHERE event_id = %d AND generation = %d ORDER BY start_utc ASC, public_key ASC',
			$table,
			$event_id,
			$generation
		);
		$rows   = is_string( $sql ) ? $wpdb->get_results( $sql, ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Isolated fixture querying the plugin-owned projection with immediately prepared SQL.
		$stored = ( new WordPressRecurrenceAggregateStore() )->load( $event_id );

		return new WP_REST_Response(
			array(
				'first_successful'     => $first->successful(),
				'first_changed'        => $first->changed(),
				'second_successful'    => $second->successful(),
				'second_changed'       => $second->changed(),
				'generation_unchanged' => 0 < $generation && (int) get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true ) === $generation,
				'healthy'              => ! metadata_exists( 'post', $event_id, EventMeta::INDEX_DIRTY ),
				'coverage_from'        => (string) get_post_meta( $event_id, EventMeta::COVERAGE_FROM, true ),
				'coverage_through'     => (string) get_post_meta( $event_id, EventMeta::COVERAGE_THROUGH, true ),
				'coverage_generation'  => (int) get_post_meta( $event_id, EventMeta::COVERAGE_GENERATION, true ),
				'aggregate_loaded'     => $stored instanceof RecurrenceAggregate && $uid === $stored->series_uid,
				'rows'                 => is_array( $rows ) ? $rows : array(),
			)
		);
	}

	/**
	 * Compare one-off membership, order, filtering, privacy and pagination.
	 *
	 * @param WP_REST_Request $request Validated administrator request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function probe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$event_id           = (int) $request['event_id'];
		$protected_event_id = (int) $request['protected_event_id'];
		$draft_event_id     = (int) $request['draft_event_id'];

		if ( min( $event_id, $protected_event_id, $draft_event_id ) <= 0 ) {
			return new WP_Error( 'wpse_smoke_invalid_id', __( 'The smoke probe requires positive event IDs.', 'mime-simple-events-calendar' ), array( 'status' => 400 ) );
		}

		$legacy      = new EventRepository();
		$occurrences = new OccurrenceReadRepository();
		$now         = time();
		$page_one    = new EventQueryCriteria( EventPeriod::ALL, 2, 1, array(), array(), $now );
		$page_two    = new EventQueryCriteria( EventPeriod::ALL, 2, 2, array(), array(), $now );
		$filtered    = new EventQueryCriteria( EventPeriod::ALL, 10, 1, array( 'calendar-smoke' ), array( 'block-smoke' ), $now );
		$window      = new EventWindowCriteria(
			new CalendarWindow( (string) $request['window_start'], (string) $request['window_end'] ),
			100,
			1,
			array(),
			array()
		);

		$legacy_one        = $legacy->query( $page_one );
		$occurrence_one    = $occurrences->query( $page_one );
		$legacy_two        = $legacy->query( $page_two );
		$occurrence_two    = $occurrences->query( $page_two );
		$legacy_filter     = $legacy->query( $filtered );
		$occurrence_filter = $occurrences->query( $filtered );
		$legacy_window     = $legacy->query_window( $window );
		$occurrence_window = $occurrences->query_window( $window );
		$window_ids        = self::occurrence_ids( $occurrence_window->occurrences );
		$exact_candidate   = null;

		foreach ( $occurrence_window->occurrences as $candidate ) {
			if ( $candidate->event_id === $event_id ) {
				$exact_candidate = $candidate;
				break;
			}
		}

		$exact_occurrence = null !== $exact_candidate
			? $occurrences->find_public( $event_id, $exact_candidate->public_key )
			: null;

		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return new WP_Error( 'wpse_smoke_no_database', __( 'The smoke probe database is unavailable.', 'mime-simple-events-calendar' ), array( 'status' => 500 ) );
		}

		$generation = (int) get_post_meta( $event_id, '_wpse_occurrence_generation', true );
		$uid        = (string) get_post_meta( $event_id, '_wpse_series_uid', true );
		$table      = $wpdb->prefix . 'wpse_event_occurrences';
		$sql        = $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_id = %d AND generation = %d', $table, $event_id, $generation );
		$row_count  = is_string( $sql ) ? (int) $wpdb->get_var( $sql ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Isolated test fixture querying only the plugin-owned projection with immediately prepared SQL.

		update_option( 'wpse_occurrence_index_migration_complete', true, false );
		$registered_meta = get_registered_meta_keys( 'post', EventPostType::POST_TYPE );
		$recurrence_meta = $registered_meta[ EventMeta::RECURRENCE ] ?? array();

		return new WP_REST_Response(
			array(
				'health'          => array(
					'generation'            => $generation,
					'uid_valid'             => 1 === preg_match( '/^[0-9a-f-]{36}$/D', $uid ),
					'row_count'             => $row_count,
					'exact_public_identity' => null !== $exact_candidate
						&& null !== $exact_occurrence
						&& $exact_candidate->event_id === $exact_occurrence->event_id
						&& hash_equals( $exact_candidate->public_key, $exact_occurrence->public_key ),
				),
				'page_one'        => array(
					'legacy_ids'             => self::post_ids( $legacy_one->posts ),
					'occurrence_ids'         => self::occurrence_ids( $occurrence_one->occurrences ),
					'legacy_total'           => (int) $legacy_one->found_posts,
					'occurrence_total'       => $occurrence_one->total,
					'legacy_total_pages'     => (int) $legacy_one->max_num_pages,
					'occurrence_total_pages' => $occurrence_one->total_pages,
				),
				'page_two'        => array(
					'legacy_ids'     => self::post_ids( $legacy_two->posts ),
					'occurrence_ids' => self::occurrence_ids( $occurrence_two->occurrences ),
				),
				'filtered'        => array(
					'legacy_ids'     => self::post_ids( $legacy_filter->posts ),
					'occurrence_ids' => self::occurrence_ids( $occurrence_filter->occurrences ),
				),
				'window'          => array(
					'legacy_ids'                => self::post_ids( $legacy_window->posts ),
					'occurrence_ids'            => $window_ids,
					'protected_parent_excluded' => ! in_array( $protected_event_id, $window_ids, true ),
					'draft_parent_excluded'     => ! in_array( $draft_event_id, $window_ids, true ),
				),
				'ready'           => ( new OccurrenceReadiness() )->ready(),
				'recurrence_meta' => array(
					'registered_as_string' => 'string' === ( $recurrence_meta['type'] ?? null ),
					'rest_hidden'          => false === ( $recurrence_meta['show_in_rest'] ?? null ),
					'revisioned'           => in_array( EventMeta::RECURRENCE, wp_post_revision_meta_keys( EventPostType::POST_TYPE ), true ),
				),
			)
		);
	}

	/**
	 * Return one positive integer route argument.
	 *
	 * @return array<string, mixed>
	 */
	private static function positive_id_argument(): array {
		return array(
			'required'          => true,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Return one text date route argument.
	 *
	 * @return array<string, mixed>
	 */
	private static function date_argument(): array {
		return array(
			'required'          => true,
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Extract canonical post IDs without changing WP_Query order.
	 *
	 * @param array $posts WordPress query posts.
	 * @return list<int>
	 * @phpstan-param array<int, WP_Post> $posts
	 */
	private static function post_ids( array $posts ): array {
		return array_map( static fn ( WP_Post $post ): int => $post->ID, $posts );
	}

	/**
	 * Extract occurrence event IDs without collapsing repeated series.
	 *
	 * @param array $occurrences Occurrence result rows.
	 * @return list<int>
	 * @phpstan-param list<OccurrenceReadModel> $occurrences
	 */
	private static function occurrence_ids( array $occurrences ): array {
		return array_map( static fn ( OccurrenceReadModel $occurrence ): int => $occurrence->event_id, $occurrences );
	}

	/** Print deterministic markers for every documented third-party canonical filter. */
	public static function output_seo_canonicals(): void {
		if ( ! is_singular( EventPostType::POST_TYPE ) ) {
			return;
		}

		foreach (
			array(
				'wpseo_canonical'              => 'yoast',
				'rank_math/frontend/canonical' => 'rank-math',
				'aioseo_canonical_url'         => 'aioseo',
			) as $filter => $marker
		) {
			$canonical = apply_filters( $filter, 'https://example.com/original-canonical/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Isolated fixture intentionally probes documented third-party filter names.

			if ( ! is_string( $canonical ) || '' === $canonical ) {
				continue;
			}

			echo '<meta name="wpse-smoke-' . esc_attr( $marker ) . '-canonical" content="' . esc_url( $canonical ) . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic fragment is escaped at this isolated fixture boundary.
		}
	}
}

add_action( 'rest_api_init', array( SmokeOccurrenceProbe::class, 'register' ) );
add_action( 'wp_head', array( SmokeOccurrenceProbe::class, 'output_seo_canonicals' ), 30 );
