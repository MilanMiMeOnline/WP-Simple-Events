<?php
/**
 * Event-details shortcode attributes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

/**
 * Normalizes the optional explicit event selection without coercion.
 */
final readonly class EventDetailsAttributes {
	/**
	 * Store the normalized selection.
	 *
	 * @param int|null $event_id       Explicit event ID, when valid.
	 * @param bool     $has_explicit_id Whether an ID attribute was supplied.
	 */
	private function __construct(
		public ?int $event_id,
		public bool $has_explicit_id
	) {}

	/**
	 * Normalize allowlisted shortcode attributes.
	 *
	 * @param array<string, mixed> $attributes Raw shortcode attributes.
	 */
	public static function from_shortcode( array $attributes ): self {
		if ( ! array_key_exists( 'id', $attributes ) ) {
			return new self( null, false );
		}

		$value = $attributes['id'];

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return new self( null, true );
		}

		$string = trim( (string) $value );

		if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $string ) ) {
			return new self( null, true );
		}

		$event_id = filter_var( $string, FILTER_VALIDATE_INT );

		return new self( false === $event_id ? null : $event_id, true );
	}
}
