<?php
/**
 * Tests for the exact public occurrence REST boundary.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Rest\OccurrenceRestController;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

#[CoversClass( OccurrenceRestController::class )]
/** Proves exact matching, generic absence and strict route identifiers. */
final class OccurrenceRestControllerTest extends TestCase {
	private const KEY = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** One matching public identity returns its bounded occurrence resource. */
	public function test_returns_one_exact_occurrence(): void {
		$provider          = new FakeOccurrencePresentationProvider();
		$provider->context = OccurrencePresentationFixture::create( $this->series(), self::KEY );
		$controller        = new OccurrenceRestController( $provider, new OccurrenceRouteController( $provider ) );
		$response          = $controller->get_item( $this->request( 42, self::KEY ) );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( self::KEY, $response->get_data()['occurrence_key'] );
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

	/** Missing and mismatched contexts share one non-disclosing 404 response. */
	public function test_missing_and_mismatched_occurrences_are_indistinguishable(): void {
		$missing = new FakeOccurrencePresentationProvider();
		$absent  = ( new OccurrenceRestController( $missing ) )->get_item( $this->request( 42, self::KEY ) );

		$mismatch          = new FakeOccurrencePresentationProvider();
		$mismatch->context = OccurrencePresentationFixture::create( $this->series(), self::KEY );
		$wrong             = ( new OccurrenceRestController( $mismatch ) )->get_item( $this->request( 43, self::KEY ) );

		foreach ( array( $absent, $wrong ) as $error ) {
			self::assertInstanceOf( WP_Error::class, $error );
			self::assertSame( 'wpse_occurrence_not_found', $error->get_error_code() );
			self::assertSame( array( 'status' => 404 ), $error->get_error_data() );
		}
	}

	/** Route identifiers reject weak numerics, uppercase and malformed keys. */
	public function test_identifier_validation_is_strict(): void {
		$controller = new OccurrenceRestController();

		self::assertTrue( $controller->valid_event_id( 42 ) );
		self::assertTrue( $controller->valid_event_id( '42' ) );
		self::assertFalse( $controller->valid_event_id( 0 ) );
		self::assertFalse( $controller->valid_event_id( '042' ) );
		self::assertFalse( $controller->valid_event_id( 42.0 ) );
		self::assertTrue( $controller->valid_occurrence_key( self::KEY ) );
		self::assertFalse( $controller->valid_occurrence_key( strtoupper( self::KEY ) ) );
		self::assertFalse( $controller->valid_occurrence_key( substr( self::KEY, 1 ) ) );
	}

	/**
	 * Build one exact request.
	 *
	 * @param int    $event_id   Canonical event ID.
	 * @param string $public_key Exact public occurrence key.
	 */
	private function request( int $event_id, string $public_key ): WP_REST_Request {
		$request = new WP_REST_Request();
		$request->set_param( 'event_id', $event_id );
		$request->set_param( 'occurrence', $public_key );

		return $request;
	}

	/** Build one published series presentation. */
	private function series(): EventPresentation {
		return new EventPresentation(
			new WP_Post(
				array(
					'ID'          => 42,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => 'Series title',
				)
			),
			'Series title',
			'https://example.com/events/series/',
			false,
			null,
			EventStatus::SCHEDULED,
			'',
			'',
			'',
			'',
			'',
			array(),
			array()
		);
	}
}
