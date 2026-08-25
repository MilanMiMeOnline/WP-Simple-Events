<?php
/**
 * Tests for current series and occurrence presentation resolution.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

#[CoversClass( CurrentEventPresentationResolver::class )]
/** Proves strict switching between ordinary current and exact occurrence contexts. */
final class CurrentEventPresentationResolverTest extends TestCase {
	private const KEY = 'cccccccccccccccccccccccccccccccc';

	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
		$this->add_event( 701, 'Series one', 'https://example.com/events/series-one/' );
		$this->add_event( 702, 'Series two', 'https://example.com/events/series-two/' );
	}

	/** A validated matching occurrence replaces only the current series presentation. */
	public function test_resolves_matching_occurrence_but_not_another_current_event(): void {
		$contexts = new EventContextResolver();
		$route    = $this->route( $contexts, 701 );
		$resolver = new CurrentEventPresentationResolver( $contexts, $route );

		$occurrence = $resolver->resolve( 701 );
		$other      = $resolver->resolve( 702 );

		self::assertNotNull( $occurrence );
		self::assertSame( 'Occurrence block title', $occurrence->title );
		self::assertSame(
			'https://example.com/events/series-one/occurrence/' . self::KEY . '/',
			$occurrence->permalink
		);
		self::assertNotNull( $other );
		self::assertSame( 'Series two', $other->title );
	}

	/** Without an occurrence route the established current-event preview remains unchanged. */
	public function test_preserves_ordinary_current_event_resolution(): void {
		$presentation = ( new CurrentEventPresentationResolver() )->resolve( 701 );

		self::assertNotNull( $presentation );
		self::assertSame( 'Series one', $presentation->title );
		self::assertSame( 'https://example.com/events/series-one/', $presentation->permalink );
	}

	/** An invalid canonical occurrence fails closed instead of showing series-owned dates. */
	public function test_invalid_occurrence_canonical_does_not_fall_back_to_series(): void {
		$this->add_event( 703, 'Unsafe series', 'javascript:alert(1)' );
		$contexts = new EventContextResolver();
		$route    = $this->route( $contexts, 703 );

		self::assertNull( ( new CurrentEventPresentationResolver( $contexts, $route ) )->resolve( 703 ) );
	}

	/**
	 * Resolve one route over an existing public series.
	 *
	 * @param EventContextResolver $contexts Shared series resolver.
	 * @param int                  $event_id Event post ID.
	 */
	private function route( EventContextResolver $contexts, int $event_id ): OccurrenceRouteController {
		$series = $contexts->resolve_public( $event_id );

		self::assertNotNull( $series );
		$provider          = new FakeOccurrencePresentationProvider();
		$provider->context = OccurrencePresentationFixture::create( $series, self::KEY );
		$route             = new OccurrenceRouteController( $provider );
		$query             = new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => EventPostType::POST_TYPE,
				'p'                                  => $event_id,
				OccurrenceRouteController::QUERY_VAR => self::KEY,
			)
		);

		self::assertNotNull( $route->resolve( $query ) );

		return $route;
	}

	/**
	 * Add one complete public event fixture.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $title Event title.
	 * @param string $permalink Public event URL.
	 */
	private function add_event( int $event_id, string $title, string $permalink ): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => $event_id,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $title,
				)
			),
			$permalink
		);
		WordPressState::update_post_meta( $event_id, EventMeta::START_UTC, 1_784_544_000 );
		WordPressState::update_post_meta( $event_id, EventMeta::END_UTC, 1_784_547_600 );
		WordPressState::update_post_meta( $event_id, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( $event_id, EventMeta::TIMEZONE, 'Europe/Brussels' );
	}
}
