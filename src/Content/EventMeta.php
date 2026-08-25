<?php
/**
 * Event metadata registration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Content;

use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateJsonCodec;

/**
 * Registers typed, single-value event metadata.
 */
final class EventMeta {
	public const START_LOCAL         = '_wpse_start_local';
	public const END_LOCAL           = '_wpse_end_local';
	public const START_UTC           = '_wpse_start_utc';
	public const END_UTC             = '_wpse_end_utc';
	public const ALL_DAY             = '_wpse_all_day';
	public const TIMEZONE            = '_wpse_timezone';
	public const VENUE               = '_wpse_venue';
	public const ADDRESS             = '_wpse_address';
	public const LOCATION_URL        = '_wpse_location_url';
	public const EVENT_URL           = '_wpse_event_url';
	public const EVENT_URL_LABEL     = '_wpse_event_url_label';
	public const STATUS              = '_wpse_event_status';
	public const DATES_NEED_REVIEW   = '_wpse_dates_need_review';
	public const SERIES_UID          = '_wpse_series_uid';
	public const ACTIVE_GENERATION   = '_wpse_occurrence_generation';
	public const INDEX_DIRTY         = '_wpse_occurrence_index_dirty';
	public const COVERAGE_FROM       = '_wpse_occurrence_coverage_from';
	public const COVERAGE_THROUGH    = '_wpse_occurrence_coverage_through';
	public const COVERAGE_GENERATION = '_wpse_occurrence_coverage_generation';
	public const RECURRENCE          = '_wpse_recurrence_definition';

	/**
	 * Register all event meta fields.
	 */
	public function register(): void {
		foreach ( $this->definitions() as $meta_key => $arguments ) {
			register_post_meta( EventPostType::POST_TYPE, $meta_key, $arguments );
		}
	}

	/**
	 * Build typed metadata definitions.
	 *
	 * UTC index values remain internal and are never writable through core REST.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function definitions(): array {
		$sanitizer                    = new EventMetaSanitizer();
		$recurrence                   = new RecurrenceAggregateJsonCodec();
		$common                       = array(
			'single'            => true,
			'auth_callback'     => array( $sanitizer, 'authorize' ),
			'revisions_enabled' => true,
		);
		$derived                      = $common;
		$derived['revisions_enabled'] = false;

		return array(
			self::START_LOCAL         => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event start', 'mime-simple-events-calendar' ),
				'description'       => __( 'Canonical local event start.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'local_datetime' ),
				'show_in_rest'      => true,
			),
			self::END_LOCAL           => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event end', 'mime-simple-events-calendar' ),
				'description'       => __( 'Canonical local event end.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'local_datetime' ),
				'show_in_rest'      => true,
			),
			self::START_UTC           => $common + array(
				'type'              => 'integer',
				'label'             => __( 'Event start UTC index', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal UTC start timestamp used for sorting.', 'mime-simple-events-calendar' ),
				'default'           => 0,
				'sanitize_callback' => array( $sanitizer, 'timestamp' ),
				'show_in_rest'      => false,
			),
			self::END_UTC             => $common + array(
				'type'              => 'integer',
				'label'             => __( 'Event end UTC index', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal inclusive UTC end timestamp used for chronological period queries.', 'mime-simple-events-calendar' ),
				'default'           => 0,
				'sanitize_callback' => array( $sanitizer, 'timestamp' ),
				'show_in_rest'      => false,
			),
			self::ALL_DAY             => $common + array(
				'type'              => 'boolean',
				'label'             => __( 'All-day event', 'mime-simple-events-calendar' ),
				'description'       => __( 'Whether the event uses inclusive dates without visible times.', 'mime-simple-events-calendar' ),
				'default'           => false,
				'sanitize_callback' => array( $sanitizer, 'boolean' ),
				'show_in_rest'      => true,
			),
			self::TIMEZONE            => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event timezone', 'mime-simple-events-calendar' ),
				'description'       => __( 'IANA timezone or WordPress fixed UTC offset used when the event was saved.', 'mime-simple-events-calendar' ),
				'default'           => wp_timezone_string(),
				'sanitize_callback' => array( $sanitizer, 'timezone' ),
				'show_in_rest'      => true,
			),
			self::VENUE               => $common + array(
				'type'              => 'string',
				'label'             => __( 'Venue', 'mime-simple-events-calendar' ),
				'description'       => __( 'Event location or venue name.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'venue' ),
				'show_in_rest'      => true,
			),
			self::ADDRESS             => $common + array(
				'type'              => 'string',
				'label'             => __( 'Address', 'mime-simple-events-calendar' ),
				'description'       => __( 'Readable event address.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'address' ),
				'show_in_rest'      => true,
			),
			self::LOCATION_URL        => $common + array(
				'type'              => 'string',
				'label'             => __( 'Location URL', 'mime-simple-events-calendar' ),
				'description'       => __( 'Optional external HTTP(S) route or location URL.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'url' ),
				'show_in_rest'      => true,
			),
			self::EVENT_URL           => $common + array(
				'type'              => 'string',
				'label'             => __( 'External event URL', 'mime-simple-events-calendar' ),
				'description'       => __( 'Optional external HTTP(S) information or registration URL.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'url' ),
				'show_in_rest'      => true,
			),
			self::EVENT_URL_LABEL     => $common + array(
				'type'              => 'string',
				'label'             => __( 'External event link label', 'mime-simple-events-calendar' ),
				'description'       => __( 'Optional plain-text label for the external event link.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'event_url_label' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'      => 'string',
						'maxLength' => EventMetaSanitizer::EVENT_URL_LABEL_MAX_LENGTH,
					),
				),
			),
			self::STATUS              => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event status', 'mime-simple-events-calendar' ),
				'description'       => __( 'Scheduled, cancelled or postponed; separate from publication status.', 'mime-simple-events-calendar' ),
				'default'           => EventStatus::SCHEDULED->value,
				'sanitize_callback' => array( $sanitizer, 'status' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => EventStatus::values(),
					),
				),
			),
			self::DATES_NEED_REVIEW   => $common + array(
				'type'              => 'boolean',
				'label'             => __( 'Copied event dates need review', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal editor flag set when event dates were duplicated.', 'mime-simple-events-calendar' ),
				'default'           => false,
				'sanitize_callback' => array( $sanitizer, 'boolean' ),
				'show_in_rest'      => false,
			),
			self::SERIES_UID          => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event series UID', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal immutable identity shared by an event series and its occurrences.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'uuid' ),
				'show_in_rest'      => false,
			),
			self::ACTIVE_GENERATION   => $common + array(
				'type'              => 'integer',
				'label'             => __( 'Active occurrence generation', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal generation token for the complete active occurrence projection.', 'mime-simple-events-calendar' ),
				'default'           => 0,
				'sanitize_callback' => array( $sanitizer, 'generation' ),
				'show_in_rest'      => false,
			),
			self::INDEX_DIRTY         => $common + array(
				'type'              => 'boolean',
				'label'             => __( 'Occurrence index needs repair', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal recovery marker set when occurrence projection could not complete.', 'mime-simple-events-calendar' ),
				'default'           => false,
				'sanitize_callback' => array( $sanitizer, 'boolean' ),
				'show_in_rest'      => false,
			),
			self::COVERAGE_FROM       => $derived + array(
				'type'              => 'string',
				'label'             => __( 'Occurrence coverage start', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal inclusive local start of the active recurring projection.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'local_date' ),
				'show_in_rest'      => false,
			),
			self::COVERAGE_THROUGH    => $derived + array(
				'type'              => 'string',
				'label'             => __( 'Occurrence coverage end', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal inclusive local end of the active recurring projection.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'local_date' ),
				'show_in_rest'      => false,
			),
			self::COVERAGE_GENERATION => $derived + array(
				'type'              => 'integer',
				'label'             => __( 'Occurrence coverage generation', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal generation token binding recurring coverage to its active projection.', 'mime-simple-events-calendar' ),
				'default'           => 0,
				'sanitize_callback' => array( $sanitizer, 'generation' ),
				'show_in_rest'      => false,
			),
			self::RECURRENCE          => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event recurrence definition', 'mime-simple-events-calendar' ),
				'description'       => __( 'Internal versioned recurrence aggregate.', 'mime-simple-events-calendar' ),
				'default'           => '',
				'sanitize_callback' => array( $recurrence, 'sanitize' ),
				'show_in_rest'      => false,
			),
		);
	}
}
