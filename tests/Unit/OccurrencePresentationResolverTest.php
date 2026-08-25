<?php
/**
 * Tests for exact public occurrence presentation resolution.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationResolver;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadQueryBuilder;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceReadGateway;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves every public field comes from one eligible identity and canonical series.
 */
#[CoversClass( OccurrencePresentationResolver::class )]
#[CoversClass( OccurrencePresentationContext::class )]
final class OccurrencePresentationResolverTest extends TestCase {
	private const EVENT_ID   = 42;
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';
	private const TARGET     = '2027-01-05T19:00:00';

	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => self::EVENT_ID,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => '',
					'post_title'    => 'Series title',
				)
			),
			'https://example.com/events/series-title/'
		);
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::STATUS, 'scheduled' );
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::VENUE, 'Series venue' );
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::ADDRESS, 'Series address' );
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::LOCATION_URL, 'https://example.com/series-location' );
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::EVENT_URL, 'https://example.com/series-action' );
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::EVENT_URL_LABEL, 'Series action' );
		WordPressState::update_post_meta( self::EVENT_ID, '_thumbnail_id', '77' );
	}

	/** Sparse values override exactly their field while empty and zero hide inheritance. */
	public function test_resolves_one_normalized_effective_occurrence_context(): void {
		$range     = $this->range();
		$aggregate = $this->aggregate(
			array(
				OccurrenceOverride::TITLE             => 'Occurrence title',
				OccurrenceOverride::NOTE              => 'Occurrence note',
				OccurrenceOverride::FEATURED_IMAGE_ID => 0,
				OccurrenceOverride::VENUE             => 'Occurrence venue',
				OccurrenceOverride::ADDRESS           => '',
				OccurrenceOverride::LOCATION_URL      => '',
				OccurrenceOverride::EVENT_URL         => 'https://example.com/occurrence-action',
				OccurrenceOverride::EVENT_URL_LABEL   => 'Occurrence action',
			)
		);
		$identity  = OccurrenceIdentity::from( self::SERIES_UID, self::TARGET );
		$context   = $this->resolver( $aggregate, $this->row( $range, $identity->public_key() ) )
			->resolve_public( self::EVENT_ID, $identity->public_key() );

		self::assertNotNull( $context );
		self::assertSame( self::EVENT_ID, $context->series->event->ID );
		self::assertSame( self::TARGET, $context->occurrence->recurrence_id );
		self::assertSame( 'postponed', $context->occurrence->status->value );
		self::assertSame( 'Occurrence title', $context->title );
		self::assertSame( 'Occurrence note', $context->note );
		self::assertSame( 0, $context->featured_image_id );
		self::assertSame( 'Occurrence venue', $context->venue );
		self::assertSame( '', $context->address );
		self::assertSame( '', $context->location_url );
		self::assertSame( 'https://example.com/occurrence-action', $context->event_url );
		self::assertSame( 'Occurrence action', $context->event_url_label );
	}

	/** Missing sparse fields inherit from the normalized public series snapshot. */
	public function test_inherits_unmodified_series_fields(): void {
		$range     = $this->range();
		$aggregate = $this->aggregate( array( OccurrenceOverride::NOTE => 'Only a note differs' ) );
		$identity  = OccurrenceIdentity::from( self::SERIES_UID, self::TARGET );
		$context   = $this->resolver( $aggregate, $this->row( $range, $identity->public_key() ) )
			->resolve_public( self::EVENT_ID, $identity->public_key() );

		self::assertNotNull( $context );
		self::assertSame( 'Series title', $context->title );
		self::assertSame( 77, $context->featured_image_id );
		self::assertSame( 'Series venue', $context->venue );
		self::assertSame( 'Series address', $context->address );
		self::assertSame( 'https://example.com/series-location', $context->location_url );
		self::assertSame( 'https://example.com/series-action', $context->event_url );
		self::assertSame( 'Series action', $context->event_url_label );
	}

	/** Repeated atomic consumers reuse one exact context during the request. */
	public function test_reuses_the_exact_resolved_context_within_the_request(): void {
		$range     = $this->range();
		$aggregate = $this->aggregate( array() );
		$identity  = OccurrenceIdentity::from( self::SERIES_UID, self::TARGET );
		$resolver  = $this->resolver( $aggregate, $this->row( $range, $identity->public_key() ) );

		$first  = $resolver->resolve_public( self::EVENT_ID, $identity->public_key() );
		$second = $resolver->resolve_public( self::EVENT_ID, $identity->public_key() );

		self::assertNotNull( $first );
		self::assertSame( $first, $second );
	}

	/** Collection rows reuse their validated projection without an exact-row SQL lookup. */
	public function test_resolves_an_already_read_projection_row_directly(): void {
		$range            = $this->range();
		$aggregate        = $this->aggregate( array() );
		$identity         = OccurrenceIdentity::from( self::SERIES_UID, self::TARGET );
		$row              = $this->row( $range, $identity->public_key() );
		$gateway          = new FakeOccurrenceReadGateway( array(), 0 );
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $aggregate;
		$resolver         = new OccurrencePresentationResolver(
			new OccurrenceReadRepository(
				new OccurrenceReadQueryBuilder(
					'wp_wpse_event_occurrences',
					'wp_posts',
					'wp_postmeta',
					'wp_term_relationships',
					'wp_term_taxonomy',
					'wp_terms'
				),
				$gateway
			),
			$store,
			new EventContextResolver()
		);

		$context                     = $resolver->resolve_projected( OccurrenceReadModel::from_row( $row ) );
		$second_range                = EventDateRange::from_local(
			'2027-01-06T19:00:00',
			'2027-01-06T21:00:00',
			false,
			'Europe/Brussels'
		);
		$second_identity             = OccurrenceIdentity::from( self::SERIES_UID, '2027-01-06T19:00:00' );
		$second_row                  = $this->row( $second_range, $second_identity->public_key() );
		$second_row['recurrence_id'] = '2027-01-06T19:00:00';
		$second                      = $resolver->resolve_projected( OccurrenceReadModel::from_row( $second_row ) );

		self::assertNotNull( $context );
		self::assertNotNull( $second );
		self::assertSame( self::TARGET, $context->occurrence->recurrence_id );
		self::assertSame( 1, $store->load_calls );
		self::assertNull( $gateway->rows_query );
		self::assertNull( $gateway->count_query );
	}

	/** A key that is not derived from canonical series identity fails closed. */
	public function test_rejects_projection_key_that_does_not_match_canonical_identity(): void {
		$key = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

		self::assertNull(
			$this->resolver( $this->aggregate( array() ), $this->row( $this->range(), $key ) )
				->resolve_public( self::EVENT_ID, $key )
		);
	}

	/** Malformed keys and nonrecurring canonical state never create a public context. */
	public function test_rejects_malformed_or_nonrecurring_context(): void {
		$identity = OccurrenceIdentity::from( self::SERIES_UID, self::TARGET );
		$resolver = $this->resolver( null, $this->row( $this->range(), $identity->public_key() ) );

		self::assertNull( $resolver->resolve_public( self::EVENT_ID, 'NOT-A-CANONICAL-KEY' ) );
		self::assertNull( $resolver->resolve_public( self::EVENT_ID, $identity->public_key() ) );
	}

	/**
	 * Build a strict recurrence aggregate with optional target override fields.
	 *
	 * @param array $fields Sparse target override fields.
	 * @phpstan-param array<string, \MiMe\WPSimpleEvents\Domain\EventStatus|EventDateRange|int|string> $fields
	 */
	private function aggregate( array $fields ): RecurrenceAggregate {
		$overrides = array() === $fields
			? array()
			: array( OccurrenceOverride::from_fields( self::TARGET, $fields ) );

		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, self::TARGET, $this->range(), RecurrenceRule::daily() ) ),
			array(),
			array(),
			$overrides
		);
	}

	/** Return the occurrence date primitive shared by aggregate and projection. */
	private function range(): EventDateRange {
		return EventDateRange::from_local(
			'2027-01-05T19:00:00',
			'2027-01-05T21:00:00',
			false,
			'Europe/Brussels'
		);
	}

	/**
	 * Return one strict raw active projection row.
	 *
	 * @param EventDateRange $range      Effective occurrence range.
	 * @param string         $public_key Stable occurrence key.
	 * @return array<string, int|string>
	 */
	private function row( EventDateRange $range, string $public_key ): array {
		return array(
			'event_id'      => self::EVENT_ID,
			'public_key'    => $public_key,
			'recurrence_id' => self::TARGET,
			'generation'    => 123456,
			'segment_id'    => 0,
			'source'        => 'rule',
			'start_local'   => $range->start_local(),
			'end_local'     => $range->end_local(),
			'start_utc'     => $range->start_utc(),
			'end_utc'       => $range->end_utc(),
			'timezone'      => $range->timezone(),
			'all_day'       => 0,
			'event_status'  => 'postponed',
		);
	}

	/**
	 * Assemble the resolver around deterministic storage boundaries.
	 *
	 * @param RecurrenceAggregate|null  $aggregate Canonical recurrence fixture.
	 * @param array<string, int|string> $row       Raw active projection fixture.
	 */
	private function resolver(
		?RecurrenceAggregate $aggregate,
		array $row
	): OccurrencePresentationResolver {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $aggregate;
		$repository       = new OccurrenceReadRepository(
			new OccurrenceReadQueryBuilder(
				'wp_wpse_event_occurrences',
				'wp_posts',
				'wp_postmeta',
				'wp_term_relationships',
				'wp_term_taxonomy',
				'wp_terms'
			),
			new FakeOccurrenceReadGateway( array( $row ), 1 )
		);

		return new OccurrencePresentationResolver(
			$repository,
			$store,
			new EventContextResolver()
		);
	}
}
