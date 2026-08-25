<?php
/**
 * Canonical occurrence URL construction.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Routing;

/**
 * Builds one strict HTTP(S) occurrence leaf without permalink assumptions.
 */
final readonly class OccurrenceRouteUrlBuilder {
	/** Public query variable containing one exact occurrence key. */
	public const QUERY_VAR = 'wpse_occurrence';

	/**
	 * Build a pretty or query-style occurrence URL, or fail closed.
	 *
	 * @param string $series_url Canonical public series permalink.
	 * @param string $public_key Stable lowercase occurrence key.
	 */
	public function build( string $series_url, string $public_key ): string {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $public_key ) ) {
			return '';
		}

		$series_url = esc_url_raw( $series_url, array( 'http', 'https' ) );

		if ( '' === $series_url ) {
			return '';
		}

		if ( str_contains( $series_url, '?' ) ) {
			return esc_url_raw(
				add_query_arg( self::QUERY_VAR, $public_key, $series_url ),
				array( 'http', 'https' )
			);
		}

		return esc_url_raw(
			rtrim( $series_url, '/' ) . '/occurrence/' . $public_key . '/',
			array( 'http', 'https' )
		);
	}
}
