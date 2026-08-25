<?php
/**
 * Tests for optional SEO canonical compatibility.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Seo\ThirdPartyCanonicalController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

#[CoversClass( ThirdPartyCanonicalController::class )]
/** Proves supported SEO plugins receive only the exact safe occurrence URL. */
final class ThirdPartyCanonicalControllerTest extends TestCase {
	private const KEY = 'abababababababababababababababab';

	/** Reset hook state before every test. */
	protected function setUp(): void {
		HookRecorder::reset();
	}

	/** All three documented optional filters register without plugin class checks. */
	public function test_registers_supported_seo_filters(): void {
		( new ThirdPartyCanonicalController() )->register();

		self::assertIsCallable( HookRecorder::action( 'wpseo_canonical' ) );
		self::assertIsCallable( HookRecorder::action( 'rank_math/frontend/canonical' ) );
		self::assertIsCallable( HookRecorder::action( 'aioseo_canonical_url' ) );
	}

	/** A validated occurrence replaces string and suppressed host canonicals. */
	public function test_current_occurrence_uses_its_exact_canonical(): void {
		$controller = new ThirdPartyCanonicalController( $this->resolved_route( $this->series() ) );
		$expected   = 'https://example.com/events/series/occurrence/' . self::KEY . '/';

		self::assertSame( $expected, $controller->canonical( 'https://example.com/events/series/' ) );
		self::assertSame( $expected, $controller->canonical( false ) );
	}

	/** Ordinary requests preserve the host plugin value exactly. */
	public function test_non_occurrence_context_is_unchanged(): void {
		$controller = new ThirdPartyCanonicalController();

		self::assertSame( 'https://example.com/original/', $controller->canonical( 'https://example.com/original/' ) );
		self::assertFalse( $controller->canonical( false ) );
		self::assertNull( $controller->canonical( null ) );
	}

	/** An unsafe occurrence URL cannot replace a safe host canonical. */
	public function test_invalid_occurrence_canonical_fails_closed(): void {
		$controller = new ThirdPartyCanonicalController(
			$this->resolved_route( $this->series( 'javascript:alert(1)' ) )
		);

		self::assertSame(
			'https://example.com/original/',
			$controller->canonical( 'https://example.com/original/' )
		);
	}

	/**
	 * Resolve one exact route over a supplied series presentation.
	 *
	 * @param EventPresentation $series Public series presentation.
	 */
	private function resolved_route( EventPresentation $series ): OccurrenceRouteController {
		$provider          = new FakeOccurrencePresentationProvider();
		$provider->context = OccurrencePresentationFixture::create( $series, self::KEY );
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

	/**
	 * Build one published series presentation.
	 *
	 * @param string $permalink Public series URL.
	 */
	private function series( string $permalink = 'https://example.com/events/series/' ): EventPresentation {
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
			$permalink,
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
