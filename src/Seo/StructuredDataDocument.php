<?php
/**
 * Safe JSON-LD document rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

/**
 * Encodes one schema graph inside a non-executable JSON script element.
 */
final class StructuredDataDocument {
	/**
	 * Render a complete JSON-LD script element.
	 *
	 * @param array<string, mixed> $schema Schema graph.
	 */
	public function render( array $schema ): string {
		$json = wp_json_encode(
			$schema,
			JSON_UNESCAPED_SLASHES
			| JSON_UNESCAPED_UNICODE
			| JSON_HEX_TAG
			| JSON_HEX_AMP
			| JSON_HEX_APOS
			| JSON_HEX_QUOT
		);

		if ( ! is_string( $json ) ) {
			return '';
		}

		return '<script type="application/ld+json">' . $json . '</script>';
	}
}
