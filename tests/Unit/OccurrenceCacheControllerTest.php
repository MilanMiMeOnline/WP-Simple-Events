<?php
/**
 * Tests for conservative occurrence full-page cache handling.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Routing\OccurrenceCacheController;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

#[CoversClass( OccurrenceCacheController::class )]
/** Proves cache prevention is limited to an already validated occurrence leaf. */
final class OccurrenceCacheControllerTest extends TestCase {
	private const KEY = 'dddddddddddddddddddddddddddddddd';

	/** Reset deterministic hooks and headers. */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/** The policy runs after route resolution through its own WordPress hook. */
	public function test_registers_on_wp(): void {
		( new OccurrenceCacheController() )->register();

		self::assertIsCallable( HookRecorder::action( 'wp' ) );
	}

	/** A valid exact leaf receives WordPress' no-cache headers. */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_valid_occurrence_leaf_is_not_full_page_cacheable(): void {
		$route = $this->resolved_route();

		self::assertFalse( defined( 'DONOTCACHEPAGE' ) );
		self::assertSame( 0, WordPressState::nocache_header_requests() );
		( new OccurrenceCacheController( $route ) )->apply();
		self::assertTrue( defined( 'DONOTCACHEPAGE' ) );
		self::assertTrue( constant( 'DONOTCACHEPAGE' ) );
		self::assertTrue( HookRecorder::was_fired( 'litespeed_control_set_nocache' ) );
		self::assertSame( 1, WordPressState::nocache_header_requests() );
	}

	/** Ordinary requests retain their existing cache behaviour. */
	public function test_ordinary_request_is_unchanged(): void {
		( new OccurrenceCacheController() )->apply();

		self::assertFalse( HookRecorder::was_fired( 'litespeed_control_set_nocache' ) );
		self::assertSame( 0, WordPressState::nocache_header_requests() );
	}

	/** Return one route with an exact validated current occurrence. */
	private function resolved_route(): OccurrenceRouteController {
		$provider          = new FakeOccurrencePresentationProvider();
		$provider->context = OccurrencePresentationFixture::create( $this->series(), self::KEY );
		$route             = new OccurrenceRouteController( $provider );
		$query             = new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => EventPostType::POST_TYPE,
				'p'                                  => 42,
				OccurrenceRouteController::QUERY_VAR => self::KEY,
			)
		);

		self::assertNotNull( $route->resolve( $query ) );

		return $route;
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
