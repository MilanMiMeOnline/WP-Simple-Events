<?php
/**
 * Tests for the public same-origin ICS endpoint.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportController;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportResponse;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Tests\Support\FakeCalendarExportSnapshotProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Proves endpoint routing, method handling and download headers are strict. */
#[CoversClass( CalendarExportController::class )]
#[CoversClass( CalendarExportResponse::class )]
final class CalendarExportControllerTest extends TestCase {
	private const KEY = '75a59b8054f0d00a88663aab81f45ecb';

	/** A GET returns one complete non-cacheable ICS attachment. */
	public function test_returns_one_public_ics_download(): void {
		$provider           = new FakeCalendarExportSnapshotProvider();
		$provider->snapshot = $this->snapshot();
		$response           = ( new CalendarExportController( $provider ) )->response(
			'GET',
			array(
				CalendarExportController::EXPORT_QUERY_VAR => 'ics',
				CalendarExportController::EVENT_QUERY_VAR  => '42',
				CalendarExportController::OCCURRENCE_QUERY_VAR => self::KEY,
			)
		);

		self::assertNotNull( $response );
		self::assertSame( 200, $response->status );
		self::assertSame( 'text/calendar; charset=utf-8', $response->headers['Content-Type'] );
		self::assertSame( 'attachment; filename="concert-2026-07-16.ics"', $response->headers['Content-Disposition'] );
		$this->assert_private_response_headers( $response );
		self::assertStringStartsWith( "BEGIN:VCALENDAR\r\n", $response->body );
		self::assertSame(
			array(
				array(
					'event_id'   => 42,
					'public_key' => self::KEY,
				),
			),
			$provider->requests
		);
	}

	/** HEAD retains successful headers but never returns an ICS body. */
	public function test_returns_headers_only_for_head(): void {
		$provider           = new FakeCalendarExportSnapshotProvider();
		$provider->snapshot = $this->snapshot();
		$response           = ( new CalendarExportController( $provider ) )->response(
			'HEAD',
			array(
				CalendarExportController::EXPORT_QUERY_VAR => 'ics',
				CalendarExportController::EVENT_QUERY_VAR  => 42,
			)
		);

		self::assertNotNull( $response );
		self::assertSame( 200, $response->status );
		self::assertSame( '', $response->body );
		$this->assert_private_response_headers( $response );
		self::assertSame(
			array(
				array(
					'event_id'   => 42,
					'public_key' => null,
				),
			),
			$provider->requests
		);
	}

	/** Other routes are ignored while malformed and unavailable exports are 404. */
	public function test_ignores_other_routes_and_rejects_invalid_shapes(): void {
		$provider   = new FakeCalendarExportSnapshotProvider();
		$controller = new CalendarExportController( $provider );

		self::assertNull( $controller->response( 'GET', array() ) );

		foreach (
			array(
				array( CalendarExportController::EXPORT_QUERY_VAR => 'json' ),
				array(
					CalendarExportController::EXPORT_QUERY_VAR => 'ics',
					CalendarExportController::EVENT_QUERY_VAR  => '42junk',
				),
				array(
					CalendarExportController::EXPORT_QUERY_VAR     => 'ics',
					CalendarExportController::EVENT_QUERY_VAR      => '42',
					CalendarExportController::OCCURRENCE_QUERY_VAR => '',
				),
				array(
					CalendarExportController::EXPORT_QUERY_VAR     => 'ics',
					CalendarExportController::EVENT_QUERY_VAR      => '42',
					CalendarExportController::OCCURRENCE_QUERY_VAR => strtoupper( self::KEY ),
				),
			) as $query
		) {
			$response = $controller->response( 'GET', $query );
			self::assertNotNull( $response );
			self::assertSame( 404, $response->status );
			self::assertSame( '', $response->body );
			$this->assert_private_response_headers( $response );
		}

		$missing = $controller->response(
			'GET',
			array(
				CalendarExportController::EXPORT_QUERY_VAR => 'ics',
				CalendarExportController::EVENT_QUERY_VAR  => '42',
			)
		);

		self::assertNotNull( $missing );
		self::assertSame( 404, $missing->status );
		$this->assert_private_response_headers( $missing );
	}

	/** Unsupported methods are rejected before any event lookup. */
	public function test_rejects_state_changing_methods_with_allow_header(): void {
		$provider = new FakeCalendarExportSnapshotProvider();
		$response = ( new CalendarExportController( $provider ) )->response(
			'POST',
			array(
				CalendarExportController::EXPORT_QUERY_VAR => 'ics',
				CalendarExportController::EVENT_QUERY_VAR  => '42',
			)
		);

		self::assertNotNull( $response );
		self::assertSame( 405, $response->status );
		self::assertSame( 'GET, HEAD', $response->headers['Allow'] );
		$this->assert_private_response_headers( $response );
		self::assertSame( array(), $provider->requests );
	}

	/** Registered variables remain unique and response headers reject injection. */
	public function test_registers_unique_query_vars_and_rejects_unsafe_headers(): void {
		$controller = new CalendarExportController( new FakeCalendarExportSnapshotProvider() );

		self::assertSame(
			array(
				'existing',
				CalendarExportController::EVENT_QUERY_VAR,
				CalendarExportController::EXPORT_QUERY_VAR,
				CalendarExportController::OCCURRENCE_QUERY_VAR,
			),
			$controller->query_vars( array( 'existing', CalendarExportController::EVENT_QUERY_VAR ) )
		);

		$this->expectException( InvalidArgumentException::class );
		new CalendarExportResponse( 200, array( 'X-Test' => "safe\r\nInjected: yes" ), '' );
	}

	/**
	 * Assert the complete privacy and stale-cache prevention contract.
	 *
	 * @param CalendarExportResponse $response Endpoint response under test.
	 */
	private function assert_private_response_headers( CalendarExportResponse $response ): void {
		self::assertSame( 'nosniff', $response->headers['X-Content-Type-Options'] );
		self::assertSame( 'no-store, no-cache, must-revalidate, max-age=0', $response->headers['Cache-Control'] );
		self::assertSame( 'no-cache', $response->headers['Pragma'] );
		self::assertSame( 'Wed, 11 Jan 1984 05:00:00 GMT', $response->headers['Expires'] );
	}

	/** Return one deterministic public calendar snapshot. */
	private function snapshot(): CalendarExportSnapshot {
		return new CalendarExportSnapshot(
			42,
			OccurrenceIdentity::from( '019c1d83-1798-4fac-a66d-ae8d67c46319', 'one-off' ),
			'Concert',
			'https://example.com/events/concert/',
			EventDateRange::from_local(
				'2026-07-16T19:30:00',
				'2026-07-16T21:30:00',
				false,
				'Europe/Brussels'
			),
			EventStatus::SCHEDULED,
			'Details at the event page.',
			'Town Hall',
			1_784_220_000,
			'concert-2026-07-16'
		);
	}
}
