<?php
/**
 * Tests for occurrence-aware event-list shortcode rendering.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
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
use MiMe\WPSimpleEvents\Shortcode\EventListShortcode;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceReadGateway;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceTable;
use MiMe\WPSimpleEvents\Tests\Support\FakeProjectedOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( EventListShortcode::class )]
/** Proves the gated shortcode paginates and renders individual occurrences. */
final class EventListShortcodeOccurrenceTest extends TestCase {
	/**
	 * Original public query state restored after the isolated renderer test.
	 *
	 * @var array<string, mixed>
	 */
	private array $original_get;

	/** Configure a healthy projection and one public parent series. */
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
	}

	/** Restore the public request superglobal. */
	protected function tearDown(): void {
		$_GET = $this->original_get;
	}

	/** Two rows from one parent remain two linked and uniquely labelled cards. */
	public function test_renders_repeated_parent_occurrences_without_collapsing_them(): void {
		$first    = $this->occurrence( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '2027-01-05T19:00:00', '05' );
		$second   = $this->occurrence( 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', '2027-01-06T19:00:00', '06' );
		$series   = ( new EventContextResolver() )->resolve_public( 42 );
		$provider = new FakeProjectedOccurrencePresentationProvider();

		self::assertNotNull( $series );

		foreach ( array( $first, $second ) as $index => $occurrence ) {
			$provider->contexts[ $occurrence->public_key ] = new OccurrencePresentationContext(
				$series,
				$occurrence,
				'Occurrence ' . ( $index + 1 ),
				'',
				0,
				'',
				'',
				'',
				'',
				''
			);
		}

		$gateway   = new FakeOccurrenceReadGateway( array( $this->row( $first ), $this->row( $second ) ), 2 );
		$shortcode = new EventListShortcode(
			occurrences: new OccurrenceReadRepository( $this->queries(), $gateway ),
			occurrence_presenter: new OccurrenceCollectionPresenter( recurring: $provider ),
			occurrence_feature: new OccurrenceRouteFeature( true ),
			occurrence_readiness: new OccurrenceReadiness(
				new FakeOccurrenceTable(),
				new FakeOccurrenceCoverageProbe()
			)
		);
		$output    = $shortcode->render(
			array(
				'limit'          => '10',
				'filters'        => 'true',
				'filter_results' => 'true',
				'pagination'     => 'false',
				'show_excerpt'   => 'false',
				'show_image'     => 'false',
			)
		);

		self::assertSame( 2, substr_count( $output, '<article class="wpse-event-card"' ) );
		self::assertStringContainsString( 'Occurrence 1', $output );
		self::assertStringContainsString( 'Occurrence 2', $output );
		self::assertStringContainsString( '/occurrence/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/', $output );
		self::assertStringContainsString( '/occurrence/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb/', $output );
		self::assertCount( 2, $provider->requests );
		self::assertStringContainsString( '2 events found.', $output );
		self::assertNotNull( $gateway->rows_query );
		self::assertNotNull( $gateway->count_query );
	}

	/**
	 * Build one strict occurrence model for a local calendar day.
	 *
	 * @param string $public_key    Stable public occurrence key.
	 * @param string $recurrence_id Immutable recurrence identity.
	 * @param string $day           Two-digit January day.
	 */
	private function occurrence( string $public_key, string $recurrence_id, string $day ): OccurrenceReadModel {
		$range = EventDateRange::from_local(
			'2027-01-' . $day . 'T19:00:00',
			'2027-01-' . $day . 'T21:00:00',
			false,
			'Europe/Brussels'
		);

		return OccurrenceReadModel::from_row(
			array(
				'event_id'      => 42,
				'public_key'    => $public_key,
				'recurrence_id' => $recurrence_id,
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
	 * Convert one validated model to the deterministic fake database row.
	 *
	 * @param OccurrenceReadModel $occurrence Validated occurrence model.
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
			'all_day'       => $occurrence->date_range->all_day() ? 1 : 0,
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
