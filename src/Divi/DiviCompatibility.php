<?php
/**
 * Optional Divi 5 compatibility boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

/**
 * Accepts only the Divi 5 line that has passed the real-host spike.
 */
final class DiviCompatibility {
	public const MINIMUM_VERSION = '5.11.1';
	public const TESTED_VERSION  = '5.11.1';

	/**
	 * Determine whether one detected Divi version is supported.
	 *
	 * @param string|null $version Detected Divi product version.
	 */
	public static function supports( ?string $version ): bool {
		if ( null === $version || 1 !== preg_match( '/^[0-9]+\.[0-9]+(?:\.[0-9]+)?(?:[-+][0-9A-Za-z.-]+)?$/D', $version ) ) {
			return false;
		}

		return version_compare( $version, self::MINIMUM_VERSION, '>=' )
			&& version_compare( $version, '6.0.0', '<' );
	}
}
