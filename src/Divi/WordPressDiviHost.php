<?php
/**
 * WordPress-backed Divi 5 host discovery.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

/**
 * Feature-detects the active Divi 5 product without including theme files.
 */
final class WordPressDiviHost implements DiviHost {
	/** Determine whether the active theme loaded Divi 5's public product API. */
	public function is_loaded(): bool {
		return function_exists( 'et_builder_d5_enabled' )
			&& defined( 'ET_BUILDER_PRODUCT_VERSION' );
	}

	/** Return the detected Divi product version. */
	public function version(): ?string {
		if ( ! defined( 'ET_BUILDER_PRODUCT_VERSION' ) ) {
			return null;
		}

		$version = constant( 'ET_BUILDER_PRODUCT_VERSION' );

		return is_string( $version ) ? $version : null;
	}
}
