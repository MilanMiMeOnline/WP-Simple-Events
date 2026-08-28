<?php
/**
 * Tests for the occurrence-aware calendar fallback.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPresenter;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadQueryBuilder;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use MiMe\WPSimpleEvents\Shortcode\CalendarShortcode;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceReadGateway;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceTable;
use MiMe\WPSimpleEvents\Tests\Support\FakeProjectedOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( CalendarShortcode::class )]
/** Proves the accessible no-JavaScript fallback uses occurrence presentations. */
final class CalendarShortcodeOccurrenceTest extends TestCase {
	private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/**
	 * Original public query state restored after the isolated renderer test.
	 *
	 * @var array<string, mixed>
	 */
	private array $original_get;

	/** Configure healthy occurrence mode and one public series. */
	protected function setUp(): void {
		WordPressState::reset();
		$this->original_get = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test isolation snapshots a read-only public filter request.
		$_GET               = array();
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( OccurrenceIndexMigrationController::COMPLETE_OPTION, '1' );
		WordPressState::set_option( 'date_format', 'Y-m-d' );
		WordPressState::set_option( 'time_format', 'H:i' );
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => 42,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => '',
					'post_title'    => 'Series title',
				)
			),
			'https://example.com/events/series/'
		);
		WordPressState::update_post_meta( 42, EventMeta::STATUS, 'scheduled' );
		WordPressState::update_post_meta( 42, EventMeta::COLOR_MODE, EventColorMode::CUSTOM->value );
		WordPressState::update_post_meta( 42, EventMeta::COLOR, '#336699' );
	}

	/** Restore the public request superglobal. */
	protected function tearDown(): void {
		$_GET = $this->original_get;
	}

	/** The progressive fallback links the exact effective occurrence. */
	public function test_renders_the_effective_occurrence_in_the_fallback_list(): void {
		$occurrence = $this->occurrence();
		$series     = ( new EventContextResolver() )->resolve_public( 42 );

		self::assertNotNull( $series );

		$provider                        = new FakeProjectedOccurrencePresentationProvider();
		$provider->contexts[ self::KEY ] = new OccurrencePresentationContext(
			$series,
			$occurrence,
			'Occurrence fallback title',
			'',
			0,
			'Occurrence fallback venue',
			'',
			'',
			'',
			''
		);
		$gateway                         = new FakeOccurrenceReadGateway( array( $this->row( $occurrence ) ), 1 );
		$calendar                        = new CalendarShortcode(
			occurrences: new OccurrenceReadRepository( $this->queries(), $gateway ),
			occurrence_presenter: new OccurrenceCollectionPresenter( recurring: $provider ),
			occurrence_feature: new OccurrenceRouteFeature( true ),
			occurrence_readiness: new OccurrenceReadiness(
				new FakeOccurrenceTable(),
				new FakeOccurrenceCoverageProbe()
			)
		);
		$output                          = $calendar->render( array( 'filters' => 'false' ) );

		self::assertStringContainsString( 'Occurrence fallback title', $output );
		self::assertStringContainsString( 'Occurrence fallback venue', $output );
		self::assertStringContainsString( '/occurrence/' . self::KEY . '/', $output );
		self::assertStringContainsString( 'data-wpse-calendar=', $output );
		self::assertStringNotContainsString( 'https://example.test/wp-json/', $output );
		self::assertStringContainsString( '\/wp-json\/wpse\/v1\/events', $output );
		self::assertStringContainsString( 'wpse-event-card-has-color', $output );
		self::assertStringContainsString( '--wpse-event-color:#336699', $output );
		self::assertNotNull( $gateway->rows_query );
	}

	/** Build one strict recurring projection model. */
	private function occurrence(): OccurrenceReadModel {
		$range = EventDateRange::from_local(
			'2027-01-05T19:00:00',
			'2027-01-05T21:00:00',
			false,
			'Europe/Brussels'
		);

		return OccurrenceReadModel::from_row(
			array(
				'event_id'      => 42,
				'public_key'    => self::KEY,
				'recurrence_id' => '2027-01-05T19:00:00',
				'generation'    => 8,
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
	}

	/**
	 * Convert one validated occurrence to a fake database row.
	 *
	 * @param OccurrenceReadModel $occurrence Exact active occurrence row.
	 * @return array<string, int|string>
	 */
	private function row( OccurrenceReadModel $occurrence ): array {
		return array(
			'event_id'      => $occurrence->event_id,
			'public_key'    => $occurrence->public_key,
			'recurrence_id' => $occurrence->recurrence_id,
			'generation'    => $occurrence->generation,
			'segment_id'    => $occurrence->segment_id,
			'source'        => $occurrence->source->value,
			'start_local'   => $occurrence->date_range->start_local(),
			'end_local'     => $occurrence->date_range->end_local(),
			'start_utc'     => $occurrence->date_range->start_utc(),
			'end_utc'       => $occurrence->date_range->end_utc(),
			'timezone'      => $occurrence->date_range->timezone(),
			'all_day'       => 0,
			'event_status'  => $occurrence->status->value,
		);
	}

	/** Build the deterministic occurrence SQL planner. */
	private function queries(): OccurrenceReadQueryBuilder {
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
