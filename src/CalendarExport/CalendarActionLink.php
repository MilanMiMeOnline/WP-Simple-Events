<?php
/**
 * Immutable add-to-calendar action link.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

use InvalidArgumentException;

/** Carries one already-bounded provider destination into shared markup. */
final readonly class CalendarActionLink {
	/**
	 * Store one provider action.
	 *
	 * @param CalendarProvider $provider Provider identity.
	 * @param string           $url      Valid bounded HTTPS destination.
	 * @param bool             $external Whether the visitor leaves the site.
	 * @throws InvalidArgumentException When the destination is unsafe.
	 */
	public function __construct(
		public CalendarProvider $provider,
		public string $url,
		public bool $external
	) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

		if ( '' === $url
			|| strlen( $url ) > CalendarProviderUrlBuilder::MAX_URL_BYTES
			|| false === filter_var( $url, FILTER_VALIDATE_URL )
			|| ( $external && 'https' !== $scheme )
			|| ( ! $external && ! in_array( $scheme, array( 'http', 'https' ), true ) )
		) {
			throw new InvalidArgumentException( 'The calendar action URL is invalid.' );
		}
	}
}
