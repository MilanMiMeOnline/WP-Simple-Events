<?php
/**
 * Tests for bounded WordPress Core occurrence sitemap discovery.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadQueryBuilder;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Seo\OccurrenceSitemapProvider;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceReadGateway;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( OccurrenceSitemapProvider::class )]
/** Proves Core registration, exact validation, URL safety and bounded paging. */
final class OccurrenceSitemapProviderTest extends TestCase {
	private const KEY = 'cccccccccccccccccccccccccccccccc';

	/** Reset all deterministic WordPress state. */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/** Registration stays explicit and uses the dedicated provider name. */
	public function test_registers_with_wordpress_core_on_init(): void {
		$provider = $this->provider( array(), 0, null );

		$provider->register();
		$callback = HookRecorder::action( 'init' );

		self::assertIsCallable( $callback );
		$callback();
		self::assertSame( $provider, WordPressState::sitemap_provider( 'occurrences' ) );
	}

	/** One recurring candidate emits only its exact validated canonical. */
	public function test_emits_one_exact_validated_occurrence_url(): void {
		$context  = OccurrencePresentationFixture::create( $this->series(), self::KEY );
		$provider = $this->provider( array( $this->row( self::KEY ) ), 1, $context );

		self::assertSame(
			array(
				array(
					'loc' => 'https://example.com/events/series/occurrence/' . self::KEY . '/',
				),
			),
			$provider->get_url_list( 1 )
		);
	}

	/** Missing public context, unsafe pages and unsupported subtypes fail closed. */
	public function test_invalid_candidates_and_requests_emit_nothing(): void {
		$provider = $this->provider( array( $this->row( self::KEY ) ), 1, null );

		self::assertSame( array(), $provider->get_url_list( 1 ) );
		self::assertSame( array(), $provider->get_url_list( '01' ) );
		self::assertSame( array(), $provider->get_url_list( 1, 'unexpected' ) );
		self::assertSame( 0, $provider->get_max_num_pages( 'unexpected' ) );
	}

	/** Core limits may reduce a page but can never exceed the plugin's ceiling. */
	public function test_page_count_respects_core_limit_and_plugin_ceiling(): void {
		$provider = $this->provider( array(), 201, null );

		self::assertSame( 3, $provider->get_max_num_pages() );

		WordPressState::set_option( 'wpse_test_sitemap_max_urls', 25 );

		self::assertSame( 9, $provider->get_max_num_pages() );

		WordPressState::set_option( 'wpse_test_sitemap_max_urls', 0 );

		self::assertSame( 0, $provider->get_max_num_pages() );
		self::assertSame( array(), $provider->get_url_list( 1 ) );
	}

	/**
	 * Build a provider with deterministic storage and exact-presentation state.
	 *
	 * @param list<array<string, int|string>>                                  $rows    Projection rows.
	 * @param int                                                              $total   Matching total.
	 * @param \MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext|null $context Public context.
	 */
	private function provider( array $rows, int $total, ?\MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext $context ): OccurrenceSitemapProvider {
		$presentations          = new FakeOccurrencePresentationProvider();
		$presentations->context = $context;
		$repository             = new OccurrenceReadRepository(
			$this->builder(),
			new FakeOccurrenceReadGateway( $rows, $total )
		);
		$routes                 = new OccurrenceRouteController( $presentations );

		return new OccurrenceSitemapProvider( $repository, $presentations, $routes );
	}

	/**
	 * Build one valid recurring projection row.
	 *
	 * @param string $public_key Stable public occurrence key.
	 * @return array<string, int|string>
	 */
	private function row( string $public_key ): array {
		$range = EventDateRange::from_local(
			'2027-01-05T19:00:00',
			'2027-01-05T21:00:00',
			false,
			'Europe/Brussels'
		);

		return array(
			'event_id'      => 42,
			'public_key'    => $public_key,
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
			'event_status'  => EventStatus::SCHEDULED->value,
		);
	}

	/** Return one published series with a safe public URL. */
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

	/** Return deterministic table names for internal SQL planning. */
	private function builder(): OccurrenceReadQueryBuilder {
		return new OccurrenceReadQueryBuilder(
			'wp_wpse_event_occurrences',
			'wp_posts',
			'wp_postmeta',
			'wp_term_relationships',
			'wp_term_taxonomy',
			'wp_terms'
		);
	}
}
