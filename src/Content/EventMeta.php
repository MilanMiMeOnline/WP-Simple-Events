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
	public const EVENT_URL_LABEL   = '_wpse_event_url_label';
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
				'label'             => __( 'Event start', 'simple-events-by-mime' ),
				'description'       => __( 'Canonical local event start.', 'simple-events-by-mime' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'local_datetime' ),
				'show_in_rest'      => true,
			),
			self::END_LOCAL         => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event end', 'simple-events-by-mime' ),
				'description'       => __( 'Canonical local event end.', 'simple-events-by-mime' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'local_datetime' ),
				'show_in_rest'      => true,
			),
			self::START_UTC         => $common + array(
				'type'              => 'integer',
				'label'             => __( 'Event start UTC index', 'simple-events-by-mime' ),
				'description'       => __( 'Internal UTC start timestamp used for sorting.', 'simple-events-by-mime' ),
				'default'           => 0,
				'sanitize_callback' => array( $sanitizer, 'timestamp' ),
				'show_in_rest'      => false,
			),
			self::END_UTC           => $common + array(
				'type'              => 'integer',
				'label'             => __( 'Event end UTC index', 'simple-events-by-mime' ),
				'description'       => __( 'Internal inclusive UTC end timestamp used for chronological period queries.', 'simple-events-by-mime' ),
				'default'           => 0,
				'sanitize_callback' => array( $sanitizer, 'timestamp' ),
				'show_in_rest'      => false,
			),
			self::ALL_DAY           => $common + array(
				'type'              => 'boolean',
				'label'             => __( 'All-day event', 'simple-events-by-mime' ),
				'description'       => __( 'Whether the event uses inclusive dates without visible times.', 'simple-events-by-mime' ),
				'default'           => false,
				'sanitize_callback' => array( $sanitizer, 'boolean' ),
				'show_in_rest'      => true,
			),
			self::TIMEZONE          => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event timezone', 'simple-events-by-mime' ),
				'description'       => __( 'IANA timezone or WordPress fixed UTC offset used when the event was saved.', 'simple-events-by-mime' ),
				'default'           => wp_timezone_string(),
				'sanitize_callback' => array( $sanitizer, 'timezone' ),
				'show_in_rest'      => true,
			),
			self::VENUE             => $common + array(
				'type'              => 'string',
				'label'             => __( 'Venue', 'simple-events-by-mime' ),
				'description'       => __( 'Event location or venue name.', 'simple-events-by-mime' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'venue' ),
				'show_in_rest'      => true,
			),
			self::ADDRESS           => $common + array(
				'type'              => 'string',
				'label'             => __( 'Address', 'simple-events-by-mime' ),
				'description'       => __( 'Readable event address.', 'simple-events-by-mime' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'address' ),
				'show_in_rest'      => true,
			),
			self::LOCATION_URL      => $common + array(
				'type'              => 'string',
				'label'             => __( 'Location URL', 'simple-events-by-mime' ),
				'description'       => __( 'Optional external HTTP(S) route or location URL.', 'simple-events-by-mime' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'url' ),
				'show_in_rest'      => true,
			),
			self::EVENT_URL         => $common + array(
				'type'              => 'string',
				'label'             => __( 'External event URL', 'simple-events-by-mime' ),
				'description'       => __( 'Optional external HTTP(S) information or registration URL.', 'simple-events-by-mime' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'url' ),
				'show_in_rest'      => true,
			),
			self::EVENT_URL_LABEL   => $common + array(
				'type'              => 'string',
				'label'             => __( 'External event link label', 'simple-events-by-mime' ),
				'description'       => __( 'Optional plain-text label for the external event link.', 'simple-events-by-mime' ),
				'default'           => '',
				'sanitize_callback' => array( $sanitizer, 'event_url_label' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'      => 'string',
						'maxLength' => EventMetaSanitizer::EVENT_URL_LABEL_MAX_LENGTH,
					),
				),
			),
			self::STATUS            => $common + array(
				'type'              => 'string',
				'label'             => __( 'Event status', 'simple-events-by-mime' ),
				'description'       => __( 'Scheduled, cancelled or postponed; separate from publication status.', 'simple-events-by-mime' ),
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
				'label'             => __( 'Copied event dates need review', 'simple-events-by-mime' ),
				'description'       => __( 'Internal editor flag set when event dates were duplicated.', 'simple-events-by-mime' ),
				'default'           => false,
				'sanitize_callback' => array( $sanitizer, 'boolean' ),
				'show_in_rest'      => false,
			),
		);
	}
}
