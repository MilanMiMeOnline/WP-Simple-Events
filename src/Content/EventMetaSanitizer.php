<?php
/**
 * Event metadata sanitization and authorization.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Content;

use DateTimeImmutable;
use DateTimeZone;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Domain\HexColor;

/**
 * Provides narrow callbacks for registered event metadata.
 */
final class EventMetaSanitizer {
	public const EVENT_URL_LABEL_MAX_LENGTH = 120;
	private const UUID_PATTERN              = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

	private const URL_MAX_LENGTH      = 2048;
	private const VENUE_MAX_LENGTH    = 200;
	private const ADDRESS_MAX_LENGTH  = 500;
	private const TIMEZONE_MAX_LENGTH = 64;

	/**
	 * Authorize event metadata mutations through the event edit capability.
	 *
	 * @param bool   $allowed  Existing authorization decision.
	 * @param string $meta_key Registered meta key.
	 * @param int    $post_id  Event post ID.
	 */
	public function authorize( bool $allowed, string $meta_key, int $post_id ): bool {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Normalize a local date or local date-time string.
	 *
	 * @param mixed $value Raw value.
	 */
	public function local_datetime( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = str_replace( ' ', 'T', sanitize_text_field( $value ) );

		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );

			return false !== $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
		}

		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value ) ) {
			$value .= ':00';
		}

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/D', $value ) ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s', $value, new DateTimeZone( 'UTC' ) );

		return false !== $date && $date->format( 'Y-m-d\TH:i:s' ) === $value ? $value : '';
	}

	/**
	 * Normalize one canonical local calendar date.
	 *
	 * @param mixed $value Raw value.
	 */
	public function local_date( mixed $value ): string {
		$canonical = $this->local_datetime( $value );

		return 10 === strlen( $canonical ) ? $canonical : '';
	}

	/**
	 * Normalize a Unix timestamp used for internal sorting.
	 *
	 * @param mixed $value Raw value.
	 */
	public function timestamp( mixed $value ): int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return 0;
		}

		if ( ! is_numeric( $value ) ) {
			return 0;
		}

		return (int) $value;
	}

	/**
	 * Normalize a positive occurrence-generation token.
	 *
	 * @param mixed $value Raw value.
	 */
	public function generation( mixed $value ): int {
		$generation = $this->timestamp( $value );

		return $generation > 0 ? $generation : 0;
	}

	/**
	 * Normalize a canonical UUID without accepting arbitrary identifiers.
	 *
	 * @param mixed $value Raw value.
	 */
	public function uuid( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = strtolower( sanitize_text_field( $value ) );

		return 1 === preg_match( self::UUID_PATTERN, $value ) ? $value : '';
	}

	/**
	 * Normalize a REST-compatible boolean.
	 *
	 * @param mixed $value Raw value.
	 */
	public function boolean( mixed $value ): bool {
		if ( ! is_bool( $value ) && ! is_string( $value ) && ! is_int( $value ) ) {
			return false;
		}

		return rest_sanitize_boolean( $value );
	}

	/**
	 * Normalize a WordPress-compatible timezone identifier.
	 *
	 * @param mixed $value Raw value.
	 */
	public function timezone( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( $value );

		if ( '' === $value || strlen( $value ) > self::TIMEZONE_MAX_LENGTH ) {
			return '';
		}

		$iana_timezone    = in_array( $value, timezone_identifiers_list(), true );
		$fixed_utc_offset = 1 === preg_match( '/^[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00)$/D', $value );

		return $iana_timezone || $fixed_utc_offset ? $value : '';
	}

	/**
	 * Normalize a venue name.
	 *
	 * @param mixed $value Raw value.
	 */
	public function venue( mixed $value ): string {
		return $this->limit( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), self::VENUE_MAX_LENGTH );
	}

	/**
	 * Normalize a readable, optionally multiline address.
	 *
	 * @param mixed $value Raw value.
	 */
	public function address( mixed $value ): string {
		return $this->limit( sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' ), self::ADDRESS_MAX_LENGTH );
	}

	/**
	 * Normalize an external HTTP(S) URL.
	 *
	 * @param mixed $value Raw value.
	 */
	public function url( mixed $value ): string {
		if ( ! is_string( $value ) || strlen( $value ) > self::URL_MAX_LENGTH ) {
			return '';
		}

		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/**
	 * Normalize the optional plain-text label for the external event link.
	 *
	 * @param mixed $value Raw value.
	 */
	public function event_url_label( mixed $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		return $this->limit( sanitize_text_field( $value ), self::EVENT_URL_LABEL_MAX_LENGTH );
	}

	/**
	 * Normalize the explicit event status through an allowlist.
	 *
	 * @param mixed $value Raw value.
	 */
	public function status( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return EventStatus::SCHEDULED->value;
		}

		$status = EventStatus::tryFrom( $value );

		return null === $status ? EventStatus::SCHEDULED->value : $status->value;
	}

	/**
	 * Normalize one optional strict six-digit event color.
	 *
	 * @param mixed $value Untrusted color.
	 */
	public function color( mixed $value ): string {
		return HexColor::normalize( $value );
	}

	/**
	 * Normalize explicit editor color intent through its allowlist.
	 *
	 * @param mixed $value Untrusted mode.
	 */
	public function color_mode( mixed $value ): string {
		if ( '' === $value ) {
			return EventColorMode::AUTOMATIC->value;
		}

		$mode = is_string( $value ) ? EventColorMode::tryFrom( $value ) : null;

		return null === $mode ? EventColorMode::FALLBACK->value : $mode->value;
	}

	/**
	 * Normalize one positive bounded WordPress term ID or the empty sentinel.
	 *
	 * @param mixed $value Untrusted term ID.
	 */
	public function term_id( mixed $value ): int {
		if ( is_string( $value ) && 1 !== preg_match( '/^\d+$/D', $value ) ) {
			return 0;
		}

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return 0;
		}

		$value = (int) $value;

		return $value > 0 && $value <= 2_147_483_647 ? $value : 0;
	}

	/**
	 * Limit a sanitized string without corrupting multibyte text where possible.
	 *
	 * @param string $value  Sanitized value.
	 * @param int    $length Maximum character count.
	 */
	private function limit( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
