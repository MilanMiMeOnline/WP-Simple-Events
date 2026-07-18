<?php
/**
 * Optional Elementor compatibility boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

/**
 * Decides whether the detected Elementor host is supported.
 */
final class ElementorCompatibility {
	public const MINIMUM_VERSION = '3.35.0';
	public const TESTED_VERSION  = '4.1.5';

	/**
	 * Accept a structurally valid Elementor version at or above the minimum.
	 *
	 * A future major is allowed because the integration only uses Elementor's
	 * documented addon API. Its actual compatibility remains a release gate.
	 *
	 * @param string|null $version Detected Elementor version constant.
	 */
	public static function supports( ?string $version ): bool {
		if ( null === $version || 1 !== preg_match( '/^[0-9]+\.[0-9]+(?:\.[0-9]+)?(?:[-+][0-9A-Za-z.-]+)?$/D', $version ) ) {
			return false;
		}

		return version_compare( $version, self::MINIMUM_VERSION, '>=' );
	}
}
