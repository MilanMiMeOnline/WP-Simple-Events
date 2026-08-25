<?php
/**
 * Tests for virtual public occurrence leaf routing.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

#[CoversClass( OccurrenceRouteController::class )]
/**
 * Proves route shape, exact context binding and fail-closed 404 behaviour.
 */
final class OccurrenceRouteControllerTest extends TestCase {
	private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** The rule follows the configured archive and exposes one exact query variable. */
	public function test_registers_one_strict_route_shape(): void {
		WordPressState::set_option( EventArchiveSettings::SLUG_OPTION, 'agenda' );
		$controller = new OccurrenceRouteController( new FakeOccurrencePresentationProvider() );

		self::assertSame(
			array( 'existing', OccurrenceRouteController::QUERY_VAR ),
			$controller->query_vars( array( 'existing', OccurrenceRouteController::QUERY_VAR ) )
		);

		$controller->add_rewrite_rule();

		self::assertSame(
			array(
				array(
					'regex' => '^agenda/([^/]+)/occurrence/([a-f0-9]{32})/?$',
					'query' => 'index.php?wpse_event=$matches[1]&wpse_occurrence=$matches[2]',
					'after' => 'top',
				),
			),
			WordPressState::rewrite_rules()
		);
	}

	/** One singular event and exact identity become one reusable route context. */
	public function test_resolves_one_exact_singular_occurrence(): void {
		$provider          = new FakeOccurrencePresentationProvider();
		$provider->context = $this->context();
		$controller        = new OccurrenceRouteController( $provider );
		$query             = $this->query( self::KEY );

		$context = $controller->resolve( $query );

		self::assertSame( $provider->context, $context );
		self::assertSame( $context, $controller->current() );
		self::assertFalse( $query->is_404 );
		self::assertSame(
			array(
				array(
					'event_id'   => 42,
					'public_key' => self::KEY,
				),
			),
			$provider->requests
		);
		self::assertSame(
			'https://example.com/events/series/occurrence/' . self::KEY . '/',
			$controller->canonical_url( $context )
		);
	}

	/** Missing, malformed and non-event identities fail without series redirects. */
	public function test_invalid_occurrence_requests_become_non_cacheable_404s(): void {
		$provider   = new FakeOccurrencePresentationProvider();
		$controller = new OccurrenceRouteController( $provider );

		foreach ( array( 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'INVALID' ) as $key ) {
			$query = $this->query( $key );
			$controller->resolve( $query );

			self::assertTrue( $query->is_404 );
			self::assertSame( 404, WordPressState::response_status() );
		}

		$not_event = new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => 'post',
				'p'                                  => 42,
				OccurrenceRouteController::QUERY_VAR => self::KEY,
			)
		);
		$controller->resolve( $not_event );

		self::assertTrue( $not_event->is_404 );
		self::assertSame( 3, WordPressState::nocache_header_requests() );
		self::assertCount( 1, $provider->requests );
	}

	/** Ordinary requests stay untouched and occurrence requests never collapse to the parent. */
	public function test_preserves_ordinary_requests_and_suppresses_occurrence_redirects(): void {
		$provider   = new FakeOccurrencePresentationProvider();
		$controller = new OccurrenceRouteController( $provider );
		$ordinary   = $this->query( '' );

		self::assertNull( $controller->resolve( $ordinary ) );
		self::assertFalse( $ordinary->is_404 );

		global $wp_query;
		$wp_query = $ordinary; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated route filter requires the WordPress main-query global.
		self::assertSame( 'https://example.com/events/series/', $controller->prevent_parent_redirect( 'https://example.com/events/series/', 'https://example.com/request/' ) );

		$wp_query = $this->query( self::KEY ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated route filter requires the WordPress main-query global.
		self::assertFalse( $controller->prevent_parent_redirect( 'https://example.com/events/series/', 'https://example.com/request/' ) );
	}

	/** Plain permalinks retain the series query and add only the occurrence key. */
	public function test_builds_a_plain_permalink_without_path_assumptions(): void {
		$controller = new OccurrenceRouteController( new FakeOccurrencePresentationProvider() );
		$context    = $this->context( 'https://example.com/?wpse_event=series' );

		self::assertSame(
			'https://example.com/?wpse_event=series&wpse_occurrence=' . self::KEY,
			$controller->canonical_url( $context )
		);
	}

	/**
	 * Return one deterministic singular event query.
	 *
	 * @param string $key Occurrence query key.
	 */
	private function query( string $key ): WP_Query {
		return new WP_Query(
			array(
				'wpse_test_request'                  => 'singular',
				'post_type'                          => EventPostType::POST_TYPE,
				'p'                                  => 42,
				OccurrenceRouteController::QUERY_VAR => $key,
			)
		);
	}

	/**
	 * Build one internally consistent route presentation fixture.
	 *
	 * @param string $permalink Canonical series permalink.
	 */
	private function context( string $permalink = 'https://example.com/events/series/' ): OccurrencePresentationContext {
		$range      = EventDateRange::from_local( '2027-01-05T19:00:00', '2027-01-05T21:00:00', false, 'Europe/Brussels' );
		$occurrence = OccurrenceReadModel::from_row(
			array(
				'event_id'      => 42,
				'public_key'    => self::KEY,
				'recurrence_id' => '2027-01-05T19:00:00',
				'generation'    => 7,
				'segment_id'    => 0,
				'source'        => 'rule',
				'start_local'   => $range->start_local(),
				'end_local'     => $range->end_local(),
				'start_utc'     => $range->start_utc(),
				'end_utc'       => $range->end_utc(),
				'timezone'      => $range->timezone(),
				'all_day'       => 0,
				'event_status'  => 'scheduled',
			)
		);
		$series     = new EventPresentation(
			new WP_Post(
				array(
					'ID'            => 42,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => '',
					'post_title'    => 'Series',
				)
			),
			'Series',
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

		return new OccurrencePresentationContext( $series, $occurrence, 'Occurrence', '', 0, '', '', '', '', '' );
	}
}
