<?php
/**
 * WordPress-backed Elementor host discovery.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

/**
 * Reads only Elementor's public loaded hook and version constant.
 */
final class WordPressElementorHost implements ElementorHost {
	/** Determine whether Elementor has finished loading. */
	public function is_loaded(): bool {
		return did_action( 'elementor/loaded' ) > 0;
	}

	/** Return the installed Elementor version, when available. */
	public function version(): ?string {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return null;
		}

		$version = constant( 'ELEMENTOR_VERSION' );

		return is_string( $version ) ? $version : null;
	}
}
