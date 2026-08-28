<?php
/**
 * Immutable calendar download HTTP response.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

use InvalidArgumentException;

/**
 * Separates request decisions from the terminating WordPress response adapter.
 */
final readonly class CalendarExportResponse {
	/**
	 * Store one bounded response.
	 *
	 * @param int                   $status  HTTP response status.
	 * @param array<string, string> $headers Complete response headers.
	 * @param string                $body    GET body or the empty HEAD/error body.
	 * @throws InvalidArgumentException When the response shape is unsafe.
	 */
	public function __construct(
		public int $status,
		public array $headers,
		public string $body
	) {
		if ( ! in_array( $status, array( 200, 404, 405 ), true ) ) {
			throw new InvalidArgumentException( 'The calendar response status is unsupported.' );
		}

		foreach ( $headers as $name => $value ) {
			if ( 1 !== preg_match( '/^[A-Za-z0-9-]+$/D', $name )
				|| str_contains( $value, "\r" )
				|| str_contains( $value, "\n" )
			) {
				throw new InvalidArgumentException( 'The calendar response contains an unsafe header.' );
			}
		}
	}
}
