<?php
/**
 * Tests for occurrence-aware native event archives.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Frontend\EventArchiveRenderer;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPresenter;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadQueryBuilder;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Query\EventArchiveQuery;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceReadGateway;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceTable;
use MiMe\WPSimpleEvents\Tests\Support\FakeProjectedOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

#[CoversClass( EventArchiveQuery::class )]
#[CoversClass( EventArchiveRenderer::class )]
/** Proves native archive pagination preserves occurrence cardinality. */
final class EventArchiveOccurrenceTest extends TestCase {
	/** Configure a healthy occurrence index and one public series. */
	protected function setUp(): void {
		WordPressState::reset();
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

	/** Two rows from one parent remain separate cards and truthful query totals. */
	public function test_native_archive_preserves_repeated_parent_occurrences(): void {
		$first     = $this->occurrence( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '2027-01-05T19:00:00', '05' );
		$second    = $this->occurrence( 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', '2027-01-06T19:00:00', '06' );
		$gateway   = new FakeOccurrenceReadGateway( array( $this->row( $first ), $this->row( $second ) ), 2 );
		$provider  = new FakeProjectedOccurrencePresentationProvider();
		$series    = ( new EventContextResolver() )->resolve_public( 42 );
		$presenter = new OccurrenceCollectionPresenter( recurring: $provider );

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

		$archive = $this->archive( $gateway );
		$query   = new WP_Query();

		$archive->apply( $query );
		$shell  = $archive->occurrence_posts( null, $query );
		$output = ( new EventArchiveRenderer( query: $archive, occurrence_presenter: $presenter ) )->render( $query );

		self::assertIsArray( $shell );
		self::assertCount( 2, $shell );
		self::assertSame( 42, $shell[0]->ID );
		self::assertSame( 42, $shell[1]->ID );
		self::assertSame( 2, $query->found_posts );
		self::assertSame( 1, $query->max_num_pages );
		self::assertTrue( $query->get( 'no_found_rows' ) );
		self::assertSame( '', $query->get( 'meta_query' ) );
		self::assertSame( '', $query->get( 'wpse_occurrence_criteria' ) );
		self::assertSame( 2, substr_count( $output, '<article class="wpse-event-card"' ) );
		self::assertStringContainsString( 'Occurrence 1', $output );
		self::assertStringContainsString( 'Occurrence 2', $output );
		self::assertStringContainsString( '/occurrence/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/', $output );
		self::assertStringContainsString( '/occurrence/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb/', $output );
	}

	/** A taxonomy archive pins its occurrence query to the route's own term. */
	public function test_taxonomy_archive_passes_fixed_term_to_occurrence_query(): void {
		$gateway = new FakeOccurrenceReadGateway( array(), 0 );
		$archive = $this->archive( $gateway );
		$query   = new WP_Query(
			array(
				'wpse_test_request'       => 'taxonomy',
				'taxonomy'                => EventTaxonomies::CATEGORY,
				EventTaxonomies::CATEGORY => 'music/concerts',
			)
		);

		$archive->apply( $query );
		$archive->occurrence_posts( null, $query );

		self::assertNotNull( $gateway->rows_query );
		self::assertContains( EventTaxonomies::CATEGORY, $gateway->rows_query->parameters );
		self::assertContains( 'concerts', $gateway->rows_query->parameters );
		self::assertNotContains( EventTaxonomies::TAG, $gateway->rows_query->parameters );
	}

	/** A parent that changes after the SQL read invalidates the complete page. */
	public function test_parent_visibility_race_fails_closed_without_stale_archive_output(): void {
		$occurrence = $this->occurrence( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '2027-01-05T19:00:00', '05' );
		$gateway    = new FakeOccurrenceReadGateway( array( $this->row( $occurrence ) ), 1 );
		$archive    = $this->archive( $gateway );
		$query      = new WP_Query();

		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => 42,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'draft',
					'post_password' => '',
					'post_title'    => 'No longer public',
				)
			)
		);

		$archive->apply( $query );
		$shell  = $archive->occurrence_posts( null, $query );
		$output = ( new EventArchiveRenderer( query: $archive ) )->render( $query );

		self::assertSame( array(), $shell );
		self::assertSame( 0, $query->found_posts );
		self::assertSame( 0, $query->max_num_pages );
		self::assertStringContainsString( 'No events match your selection.', $output );
		self::assertStringNotContainsString( 'No longer public', $output );
	}

	/**
	 * Build an occurrence-aware archive adapter with deterministic storage.
	 *
	 * @param FakeOccurrenceReadGateway $gateway Deterministic occurrence storage.
	 */
	private function archive( FakeOccurrenceReadGateway $gateway ): EventArchiveQuery {
		return new EventArchiveQuery(
			occurrences: new OccurrenceReadRepository( $this->queries(), $gateway ),
			occurrence_feature: new OccurrenceRouteFeature( true ),
			occurrence_readiness: new OccurrenceReadiness(
				new FakeOccurrenceTable(),
				new FakeOccurrenceCoverageProbe()
			)
		);
	}

	/**
	 * Build one strict recurring occurrence model.
	 *
	 * @param string $public_key    Stable occurrence public key.
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
	 * Convert a model to one deterministic raw database row.
	 *
	 * @param OccurrenceReadModel $occurrence Validated occurrence model.
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
