<?php
/**
 * Tests for occurrence-aware complete event details.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventDetailsRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Shortcode\EventDetailsShortcode;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

#[CoversClass( EventDetailsShortcode::class )]
/** Protects current-occurrence and explicit-series details semantics. */
final class EventDetailsShortcodeTest extends TestCase {
	private const KEY = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

	/** Reset and create one public event. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'           => 801,
					'post_type'    => EventPostType::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => 'Series shortcode title',
					'post_content' => 'Series shortcode body',
				)
			),
			'https://example.com/events/shortcode-series/'
		);
		WordPressState::update_post_meta( 801, EventMeta::START_UTC, 1_784_544_000 );
		WordPressState::update_post_meta( 801, EventMeta::END_UTC, 1_784_547_600 );
		WordPressState::update_post_meta( 801, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( 801, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::set_singular_event( true, 801 );
	}

	/** Current details use the exact occurrence while an explicit ID stays on the series. */
	public function test_current_occurrence_and_explicit_series_are_distinct(): void {
		$contexts = new EventContextResolver();
		$series   = $contexts->resolve_public( 801 );

		self::assertNotNull( $series );
		$provider          = new FakeOccurrencePresentationProvider();
		$provider->context = OccurrencePresentationFixture::create( $series, self::KEY );
		$route             = new OccurrenceRouteController( $provider );
		$query             = new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => EventPostType::POST_TYPE,
				'p'                                  => 801,
				OccurrenceRouteController::QUERY_VAR => self::KEY,
			)
		);

		self::assertNotNull( $route->resolve( $query ) );
		$shortcode = new EventDetailsShortcode(
			new EventDetailsRenderer( contexts: $contexts ),
			new FrontendAssets(),
			new CurrentEventPresentationResolver( $contexts, $route )
		);

		$current = $shortcode->render();
		self::assertStringContainsString( 'Occurrence block title', $current );
		self::assertStringContainsString( 'Occurrence block note', $current );
		self::assertStringContainsString( 'Occurrence block venue', $current );
		self::assertStringContainsString( 'Series shortcode body', $current );

		$explicit = $shortcode->render( array( 'id' => 801 ) );
		self::assertStringContainsString( 'Series shortcode title', $explicit );
		self::assertStringNotContainsString( 'Occurrence block title', $explicit );
	}

	/** A malformed explicit ID never falls back to the current occurrence. */
	public function test_invalid_explicit_id_fails_closed(): void {
		self::assertSame( '', ( new EventDetailsShortcode() )->render( array( 'id' => 'bad' ) ) );
	}
}
