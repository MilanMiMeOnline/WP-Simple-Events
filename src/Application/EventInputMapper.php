<?php
/**
 * WordPress input adapters for event validation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Maps editor and REST structures without applying business validation.
 */
final class EventInputMapper {
	/**
	 * Map the native event meta box payload.
	 *
	 * The timezone is deliberately server-owned: an existing event keeps its
	 * stored timezone and a new event receives the current site timezone.
	 *
	 * @param array<string, mixed> $payload Raw, unslashed meta box payload.
	 * @param int                  $post_id Existing event ID, or zero.
	 */
	public function from_admin( array $payload, int $post_id ): EventInput {
		$timezone = $post_id > 0 ? $this->stored_string( $post_id, EventMeta::TIMEZONE ) : '';

		if ( '' === $timezone ) {
			$timezone = wp_timezone_string();
		}

		return new EventInput(
			$this->payload_string( $payload, 'start_date' ),
			$this->payload_string( $payload, 'start_time' ),
			$this->payload_string( $payload, 'end_date' ),
			$this->payload_string( $payload, 'end_time' ),
			isset( $payload['all_day'] ) && '1' === $this->payload_string( $payload, 'all_day' ),
			$timezone,
			$this->payload_string( $payload, 'venue' ),
			$this->payload_string( $payload, 'address' ),
			$this->payload_string( $payload, 'location_url' ),
			$this->payload_string( $payload, 'event_url' ),
			$this->payload_string( $payload, 'event_url_label' ),
			$this->payload_string( $payload, 'status', EventStatus::SCHEDULED->value )
		);
	}

	/**
	 * Merge REST metadata with the existing event record.
	 *
	 * @param array<string, mixed> $meta    REST metadata submitted in this request.
	 * @param int                  $post_id Existing event ID, or zero.
	 */
	public function from_rest( array $meta, int $post_id ): EventInput {
		$start_local = $this->rest_or_stored( $meta, EventMeta::START_LOCAL, $post_id, '' );
		$end_local   = $this->rest_or_stored( $meta, EventMeta::END_LOCAL, $post_id, '' );
		$all_day     = $this->rest_boolean_or_stored( $meta, $post_id );
		$timezone    = $this->rest_or_stored( $meta, EventMeta::TIMEZONE, $post_id, wp_timezone_string() );

		return EventInput::from_canonical(
			$start_local,
			$end_local,
			$all_day,
			$timezone,
			$this->rest_or_stored( $meta, EventMeta::VENUE, $post_id, '' ),
			$this->rest_or_stored( $meta, EventMeta::ADDRESS, $post_id, '' ),
			$this->rest_or_stored( $meta, EventMeta::LOCATION_URL, $post_id, '' ),
			$this->rest_or_stored( $meta, EventMeta::EVENT_URL, $post_id, '' ),
			$this->rest_or_stored( $meta, EventMeta::EVENT_URL_LABEL, $post_id, '' ),
			$this->rest_or_stored( $meta, EventMeta::STATUS, $post_id, EventStatus::SCHEDULED->value )
		);
	}

	/**
	 * Return a scalar payload value as a string.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @param string               $key     Payload key.
	 * @param string               $fallback Default value.
	 */
	private function payload_string( array $payload, string $key, string $fallback = '' ): string {
		if ( ! array_key_exists( $key, $payload ) || ! is_scalar( $payload[ $key ] ) ) {
			return $fallback;
		}

		return (string) $payload[ $key ];
	}

	/**
	 * Select a REST value or the existing stored value.
	 *
	 * @param array<string, mixed> $meta     REST metadata.
	 * @param string               $meta_key Registered meta key.
	 * @param int                  $post_id  Existing event ID, or zero.
	 * @param string               $fallback New event default.
	 */
	private function rest_or_stored( array $meta, string $meta_key, int $post_id, string $fallback ): string {
		if ( array_key_exists( $meta_key, $meta ) ) {
			return is_scalar( $meta[ $meta_key ] ) ? (string) $meta[ $meta_key ] : '';
		}

		if ( $post_id > 0 ) {
			$stored = $this->stored_string( $post_id, $meta_key );

			return '' !== $stored ? $stored : $fallback;
		}

		return $fallback;
	}

	/**
	 * Select and normalize a REST all-day value.
	 *
	 * @param array<string, mixed> $meta    REST metadata.
	 * @param int                  $post_id Existing event ID, or zero.
	 */
	private function rest_boolean_or_stored( array $meta, int $post_id ): bool {
		if ( array_key_exists( EventMeta::ALL_DAY, $meta ) ) {
			$value = $meta[ EventMeta::ALL_DAY ];

			return is_bool( $value ) || is_string( $value ) || is_int( $value )
				? rest_sanitize_boolean( $value )
				: false;
		}

		if ( $post_id <= 0 ) {
			return false;
		}

		$value = get_post_meta( $post_id, EventMeta::ALL_DAY, true );

		return ( is_bool( $value ) || is_string( $value ) || is_int( $value ) )
			&& rest_sanitize_boolean( $value );
	}

	/**
	 * Read one scalar stored metadata value.
	 *
	 * @param int    $post_id  Event ID.
	 * @param string $meta_key Registered meta key.
	 */
	private function stored_string( int $post_id, string $meta_key ): string {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_scalar( $value ) ? (string) $value : '';
	}
}
