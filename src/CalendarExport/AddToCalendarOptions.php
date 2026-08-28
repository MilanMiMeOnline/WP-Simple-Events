<?php
/**
 * Shared add-to-calendar presentation options.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

/** Normalizes provider, layout and label choices for every presentation host. */
final readonly class AddToCalendarOptions {
	public const LAYOUT_DROPDOWN = 'dropdown';
	public const LAYOUT_LIST     = 'list';
	public const MAX_LABEL_BYTES = 120;

	/**
	 * Store normalized shared options.
	 *
	 * @param CalendarProvider[] $providers Providers in stable display order.
	 * @param string             $layout    Allowlisted presentation layout.
	 * @param string             $label     Optional bounded summary label.
	 */
	private function __construct(
		public array $providers,
		public string $layout,
		public string $label
	) {}

	/** Return the privacy-preserving local-download default. */
	public static function defaults(): self {
		return new self( array( CalendarProvider::ICS ), self::LAYOUT_DROPDOWN, '' );
	}

	/**
	 * Normalize untrusted host values without accepting arbitrary providers.
	 *
	 * @param mixed $providers Comma list or provider array; null uses the default.
	 * @param mixed $layout    Dropdown or list.
	 * @param mixed $label     Optional plain-text label.
	 */
	public static function from_input( mixed $providers = null, mixed $layout = null, mixed $label = null ): self {
		if ( null === $providers ) {
			$requested = array( CalendarProvider::ICS->value );
		} elseif ( is_array( $providers ) ) {
			$requested = array_slice( $providers, 0, 10 );
		} else {
			$value     = is_scalar( $providers ) ? trim( (string) $providers ) : '';
			$requested = strlen( $value ) <= 100 ? array_slice( explode( ',', $value ), 0, 10 ) : array();
		}

		$selected = array();

		foreach ( $requested as $value ) {
			$value    = $value instanceof CalendarProvider ? $value->value : ( is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '' );
			$provider = CalendarProvider::tryFrom( $value );

			if ( null !== $provider ) {
				$selected[ $provider->value ] = true;

				if ( count( $selected ) === count( CalendarProvider::cases() ) ) {
					break;
				}
			}
		}

		$ordered = array_values(
			array_filter(
				CalendarProvider::cases(),
				static fn( CalendarProvider $provider ): bool => isset( $selected[ $provider->value ] )
			)
		);
		$layout  = is_scalar( $layout ) ? strtolower( trim( (string) $layout ) ) : '';
		$layout  = in_array( $layout, array( self::LAYOUT_DROPDOWN, self::LAYOUT_LIST ), true )
			? $layout
			: self::LAYOUT_DROPDOWN;
		$label   = is_scalar( $label ) ? sanitize_text_field( (string) $label ) : '';
		$label   = self::bounded_utf8( trim( $label ), self::MAX_LABEL_BYTES );

		return new self( $ordered, $layout, $label );
	}

	/**
	 * Truncate one label without splitting a UTF-8 sequence.
	 *
	 * @param string $value   Normalized label.
	 * @param int    $maximum Maximum bytes.
	 */
	private static function bounded_utf8( string $value, int $maximum ): string {
		$cut = substr( $value, 0, $maximum );

		while ( '' !== $cut && 1 !== preg_match( '//u', $cut ) ) {
			$cut = substr( $cut, 0, -1 );
		}

		return $cut;
	}
}
