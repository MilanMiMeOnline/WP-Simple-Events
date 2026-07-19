<?php
/**
 * Event metadata registration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Content;

use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Registers typed, single-value event metadata.
 */
final class EventMeta {
	public const START_LOCAL       = '_wpse_start_local';
	public const END_LOCAL         = '_wpse_end_local';
	public const START_UTC         = '_wpse_start_utc';
	public const END_UTC           = '_wpse_end_utc';
	public const ALL_DAY           = '_wpse_all_day';
	public const TIMEZONE          = '_wpse_timezone';
	public const VENUE             = '_wpse_venue';
	public const ADDRESS           = '_wpse_address';
	public const LOCATION_URL      = '_wpse_location_url';
	public const EVENT_URL         = '_wpse_event_url';
	public const STATUS            = '_wpse_event_status';
	public const DATES_NEED_REVIEW = '_wpse_dates_need_review';

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
		$sanitizer = new EventMetaSanitizer();
		$common    = array(
			'single'            => true,
			'auth_callback'     => array( $sanitizer, 'authorize' ),
			'revisions_enabled' => true,
		);

		return array(
			self::START_LOCAL       => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event start', 'wp-simple-events' ),
				'description'       => __( 'Canonical local event start.', 'wp-simple-events' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'local_datetime' ),
				'show_in_rest'      => true,
			),
			self::END_LOCAL         => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event end', 'wp-simple-events' ),
				'description'       => __( 'Canonical local event end.', 'wp-simple-events' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'local_datetime' ),
				'show_in_rest'      => true,
			),
			self::START_UTC         => $common + array(
				'type'              => 'integer',
				'label'             => __( 'Event start UTC index', 'wp-simple-events' ),
				'description'       => __( 'Internal UTC start timestamp used for sorting.', 'wp-simple-events' ),
				'default'           => 0,
				'sanitize_callback' => array( $sanitizer, 'timestamp' ),
				'show_in_rest'      => false,
			),
			self::END_UTC           => $common + array(
				'type'              => 'integer',
				'label'             => __( 'Event end UTC index', 'wp-simple-events' ),
				'description'       => __( 'Internal inclusive UTC end timestamp used for chronological period queries.', 'wp-simple-events' ),
				'default'           => 0,
				'sanitize_callback' => array( $sanitizer, 'timestamp' ),
				'show_in_rest'      => false,
			),
			self::ALL_DAY           => $common + array(
				'type'              => 'boolean',
				'label'             => __( 'All-day event', 'wp-simple-events' ),
				'description'       => __( 'Whether the event uses inclusive dates without visible times.', 'wp-simple-events' ),
				'default'           => false,
				'sanitize_callback' => array( $sanitizer, 'boolean' ),
				'show_in_rest'      => true,
			),
			self::TIMEZONE          => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event timezone', 'wp-simple-events' ),
				'description'       => __( 'IANA timezone or WordPress fixed UTC offset used when the event was saved.', 'wp-simple-events' ),
				'default'           => wp_timezone_string(),
				'sanitize_callback' => array( $sanitizer, 'timezone' ),
				'show_in_rest'      => true,
			),
			self::VENUE             => $common + array(
				'type'              => 'string',
				'label'             => __( 'Venue', 'wp-simple-events' ),
				'description'       => __( 'Event location or venue name.', 'wp-simple-events' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'venue' ),
				'show_in_rest'      => true,
			),
			self::ADDRESS           => $common + array(
				'type'              => 'string',
				'label'             => __( 'Address', 'wp-simple-events' ),
				'description'       => __( 'Readable event address.', 'wp-simple-events' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'address' ),
				'show_in_rest'      => true,
			),
			self::LOCATION_URL      => $common + array(
				'type'              => 'string',
				'label'             => __( 'Location URL', 'wp-simple-events' ),
				'description'       => __( 'Optional external HTTP(S) route or location URL.', 'wp-simple-events' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'url' ),
				'show_in_rest'      => true,
			),
			self::EVENT_URL         => $common + array(
				'type'              => 'string',
				'label'             => __( 'External event URL', 'wp-simple-events' ),
				'description'       => __( 'Optional external HTTP(S) information or registration URL.', 'wp-simple-events' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'url' ),
				'show_in_rest'      => true,
			),
			self::STATUS            => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event status', 'wp-simple-events' ),
				'description'       => __( 'Scheduled, cancelled or postponed; separate from publication status.', 'wp-simple-events' ),
				'default'           => EventStatus::SCHEDULED->value,
				'sanitize_callback' => array( $sanitizer, 'status' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => EventStatus::values(),
					),
				),
			),
			self::DATES_NEED_REVIEW => $common + array(
				'type'              => 'boolean',
				'label'             => __( 'Copied event dates need review', 'wp-simple-events' ),
				'description'       => __( 'Internal editor flag set when event dates were duplicated.', 'wp-simple-events' ),
				'default'           => false,
				'sanitize_callback' => array( $sanitizer, 'boolean' ),
				'show_in_rest'      => false,
			),
		);
	}
}
