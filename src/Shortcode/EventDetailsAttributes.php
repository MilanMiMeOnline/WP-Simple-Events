<?php
/**
 * Event-details shortcode attributes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Frontend\EventDetailsOptions;

/**
 * Normalizes the optional explicit event selection without coercion.
 */
final readonly class EventDetailsAttributes {
	/**
	 * Store the normalized selection.
	 *
	 * @param int|null            $event_id       Explicit event ID, when valid.
	 * @param bool                $has_explicit_id Whether an ID attribute was supplied.
	 * @param EventDetailsOptions $options Bounded composite presentation.
	 */
	private function __construct(
		public ?int $event_id,
		public bool $has_explicit_id,
		public EventDetailsOptions $options
	) {}

	/**
	 * Normalize allowlisted shortcode attributes.
	 *
	 * @param array<string, mixed> $attributes Raw shortcode attributes.
	 */
	public static function from_shortcode( array $attributes ): self {
		$options = self::options( $attributes );

		if ( ! array_key_exists( 'id', $attributes ) ) {
			return new self( null, false, $options );
		}

		$value = $attributes['id'];

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return new self( null, true, $options );
		}

		$string = trim( (string) $value );

		if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $string ) ) {
			return new self( null, true, $options );
		}

		$event_id = filter_var( $string, FILTER_VALIDATE_INT );

		return new self( false === $event_id ? null : $event_id, true, $options );
	}

	/**
	 * Build the bounded complete-details presentation contract.
	 *
	 * @param array<string, mixed> $attributes Raw shortcode attributes.
	 */
	private static function options( array $attributes ): EventDetailsOptions {
		return new EventDetailsOptions(
			self::boolean_value( $attributes['show_title'] ?? null, true ),
			self::boolean_value( $attributes['show_image'] ?? null, true ),
			self::boolean_value( $attributes['show_date'] ?? null, true ),
			self::boolean_value( $attributes['show_status'] ?? null, true ),
			self::boolean_value( $attributes['show_location'] ?? null, true ),
			self::boolean_value( $attributes['show_content'] ?? null, true ),
			self::boolean_value( $attributes['show_action'] ?? null, true ),
			self::boolean_value( $attributes['show_terms'] ?? null, true ),
			self::choice( $attributes['heading_level'] ?? null, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h1' ),
			self::text( $attributes['date_label'] ?? null ),
			self::text( $attributes['venue_label'] ?? null ),
			self::text( $attributes['location_label'] ?? null ),
			self::text( $attributes['action_label'] ?? null ),
			self::text( $attributes['categories_label'] ?? null ),
			self::text( $attributes['tags_label'] ?? null )
		);
	}

	/**
	 * Normalize a strict public boolean.
	 *
	 * @param mixed $value    Raw boolean value.
	 * @param bool  $fallback Invalid-value fallback.
	 */
	private static function boolean_value( mixed $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( ! is_scalar( $value ) ) {
			return $fallback;
		}

		return match ( strtolower( trim( (string) $value ) ) ) {
			'1', 'true', 'yes', 'on' => true,
			'0', 'false', 'no', 'off' => false,
			default => $fallback,
		};
	}

	/**
	 * Normalize one allowlisted string.
	 *
	 * @param mixed    $value    Raw choice value.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Invalid-value fallback.
	 */
	private static function choice( mixed $value, array $allowed, string $fallback ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Normalize one short plain-text label.
	 *
	 * @param mixed $value Raw label value.
	 */
	private static function text( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return substr( trim( sanitize_text_field( (string) $value ) ), 0, 120 );
	}
}
