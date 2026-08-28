<?php
/**
 * Public same-origin calendar download endpoint.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

/**
 * Maps one strict GET/HEAD query into a non-cacheable ICS response.
 */
final readonly class CalendarExportController {
	public const EXPORT_QUERY_VAR     = 'wpse_calendar_export';
	public const EVENT_QUERY_VAR      = 'wpse_event';
	public const OCCURRENCE_QUERY_VAR = 'wpse_occurrence';

	/**
	 * Create the endpoint.
	 *
	 * @param CalendarExportSnapshotProvider $snapshots Public snapshot resolver.
	 * @param IcsCalendarBuilder             $calendar  Standards-based ICS builder.
	 */
	public function __construct(
		private CalendarExportSnapshotProvider $snapshots = new CalendarExportSnapshotResolver(),
		private IcsCalendarBuilder $calendar = new IcsCalendarBuilder()
	) {}

	/** Register public query discovery and the late request adapter. */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve_current_request' ), 0 );
	}

	/**
	 * Register only the endpoint's strict query variables.
	 *
	 * @param string[] $query_vars Existing public query variables.
	 * @return string[]
	 */
	public function query_vars( array $query_vars ): array {
		$query_vars[] = self::EXPORT_QUERY_VAR;
		$query_vars[] = self::EVENT_QUERY_VAR;
		$query_vars[] = self::OCCURRENCE_QUERY_VAR;

		return array_values( array_unique( $query_vars ) );
	}

	/**
	 * Build a response for one validated request shape, or ignore another route.
	 *
	 * @param string               $method Uppercase HTTP method.
	 * @param array<string, mixed> $query  Untrusted query values.
	 */
	public function response( string $method, array $query ): ?CalendarExportResponse {
		if ( ! array_key_exists( self::EXPORT_QUERY_VAR, $query ) ) {
			return null;
		}

		if ( 'ics' !== $query[ self::EXPORT_QUERY_VAR ] ) {
			return $this->error( 404 );
		}

		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return $this->error( 405 );
		}

		$event_id = $this->positive_integer( $query[ self::EVENT_QUERY_VAR ] ?? null );
		$key      = null;

		if ( array_key_exists( self::OCCURRENCE_QUERY_VAR, $query ) ) {
			$value = $query[ self::OCCURRENCE_QUERY_VAR ];

			if ( ! is_string( $value ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $value ) ) {
				return $this->error( 404 );
			}

			$key = $value;
		}

		if ( 0 === $event_id ) {
			return $this->error( 404 );
		}

		$snapshot = $this->snapshots->resolve( $event_id, $key );

		if ( null === $snapshot ) {
			return $this->error( 404 );
		}

		$body = $this->calendar->build( $snapshot );

		return new CalendarExportResponse(
			200,
			array(
				'Content-Type'           => 'text/calendar; charset=utf-8',
				'Content-Disposition'    => 'attachment; filename="' . $snapshot->filename . '.ics"',
				'X-Content-Type-Options' => 'nosniff',
				'Cache-Control'          => 'no-store, no-cache, must-revalidate, max-age=0',
				'Pragma'                 => 'no-cache',
				'Expires'                => 'Wed, 11 Jan 1984 05:00:00 GMT',
			),
			'HEAD' === $method ? '' : $body
		);
	}

	/** Serve and terminate only when this request explicitly targets the endpoint. */
	public function serve_current_request(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only endpoint; the complete request is validated before resolution.
		$query = wp_unslash( $_GET );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$response = $this->response( $method, $query );

		if ( null === $response ) {
			return;
		}

		status_header( $response->status );

		foreach ( $response->headers as $name => $value ) {
			header( $name . ': ' . $value, true );
		}

		if ( '' !== $response->body ) {
			echo $response->body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully encoded RFC 5545 document, not HTML.
		}

		exit;
	}

	/**
	 * Parse one strict positive decimal event ID without accepting coercion.
	 *
	 * @param mixed $value Untrusted query value.
	 */
	private function positive_integer( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9]\d*$/D', $value ) ) {
			return 0;
		}

		$event_id = filter_var( $value, FILTER_VALIDATE_INT );

		return false !== $event_id && $event_id > 0 ? $event_id : 0;
	}

	/**
	 * Return one empty, non-disclosing and non-cacheable error response.
	 *
	 * @param int $status Allowlisted 404 or 405 status.
	 */
	private function error( int $status ): CalendarExportResponse {
		$headers = array(
			'Content-Type'           => 'text/plain; charset=utf-8',
			'X-Content-Type-Options' => 'nosniff',
			'Cache-Control'          => 'no-store, no-cache, must-revalidate, max-age=0',
			'Pragma'                 => 'no-cache',
			'Expires'                => 'Wed, 11 Jan 1984 05:00:00 GMT',
		);

		if ( 405 === $status ) {
			$headers['Allow'] = 'GET, HEAD';
		}

		return new CalendarExportResponse( $status, $headers, '' );
	}
}
