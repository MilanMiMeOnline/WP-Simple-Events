<?php
/**
 * Stored event color intent.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/** Explicit series-level editor color choices. */
enum EventColorMode: string {
	case AUTOMATIC = 'automatic';
	case FALLBACK  = 'fallback';
	case CATEGORY  = 'category';
	case CUSTOM    = 'custom';

	/**
	 * Read stored intent; missing metadata is the migration-free automatic mode.
	 *
	 * @param mixed $value Untrusted stored value.
	 */
	public static function from_stored( mixed $value ): ?self {
		if ( '' === $value ) {
			return self::AUTOMATIC;
		}

		return is_string( $value ) ? self::tryFrom( $value ) : null;
	}

	/**
	 * Return all values accepted at metadata boundaries.
	 *
	 * @return list<string>
	 */
	public static function values(): array {
		return array_column( self::cases(), 'value' );
	}
}
