<?php
/**
 * Tests for public add-to-calendar snapshot resolution.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshotResolver;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadQueryBuilder;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceReadGateway;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves exports never cross public one-off and recurring occurrence boundaries.
 */
#[CoversClass( CalendarExportSnapshotResolver::class )]
final class CalendarExportSnapshotResolverTest extends TestCase {
	private const EVENT_ID   = 42;
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';
	private const TARGET     = '2027-01-05T19:00:00';

	/** Reset deterministic public event state. */
	protected function setUp(): void {
		WordPressState::reset();
		$this->add_event();
	}

	/** A public one-off uses its active projection and canonical public content. */
	public function test_resolves_one_public_one_off_snapshot(): void {
		$identity = OccurrenceIdentity::from( self::SERIES_UID, 'one-off' );
		$resolver = $this->resolver( array( $this->row( $this->range(), $identity->public_key(), 'one_off', 'one-off' ) ) );
		$snapshot = $resolver->resolve( self::EVENT_ID );

		self::assertNotNull( $snapshot );
		self::assertSame( $identity->public_key(), $snapshot->identity->public_key() );
		self::assertSame( 'Calendar title', $snapshot->title );
		self::assertSame( 'https://example.com/events/calendar-title/', $snapshot->canonical_url );
		self::assertSame( "Public excerpt\n\nhttps://example.com/events/calendar-title/", $snapshot->description );
		self::assertSame( "Main hall\nMain Street 1", $snapshot->location );
		self::assertSame( 1_799_107_200, $snapshot->last_modified_utc );
		self::assertSame( 'calendar-title-2027-01-05', $snapshot->filename );
	}

	/** One exact occurrence exports its effective fields and canonical leaf URL only. */
	public function test_resolves_one_exact_recurring_occurrence_snapshot(): void {
		$identity           = OccurrenceIdentity::from( self::SERIES_UID, self::TARGET );
		$presenter          = new FakeOccurrencePresentationProvider();
		$series             = ( new EventContextResolver() )->resolve_public( self::EVENT_ID );
		$presenter->context = null === $series ? null : OccurrencePresentationFixture::create( $series, $identity->public_key() );
		$resolver           = $this->resolver( array(), null, $presenter );
		$snapshot           = $resolver->resolve( self::EVENT_ID, $identity->public_key() );

		self::assertNotNull( $snapshot );
		self::assertSame( 'Occurrence block title', $snapshot->title );
		self::assertSame( 'Occurrence block note', strstr( $snapshot->description, "\n", true ) );
		self::assertSame( "Occurrence block venue\nOccurrence block address", $snapshot->location );
		self::assertSame(
			'https://example.com/events/calendar-title/occurrence/' . $identity->public_key() . '/',
			$snapshot->canonical_url
		);
		self::assertSame(
			array(
				array(
					'event_id'   => self::EVENT_ID,
					'public_key' => $identity->public_key(),
				),
			),
			$presenter->requests
		);
	}

	/** A recurring series page never guesses or exports one of its occurrences. */
	public function test_rejects_recurring_series_without_an_exact_occurrence_key(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$identity         = OccurrenceIdentity::from( self::SERIES_UID, 'one-off' );
		$resolver         = $this->resolver(
			array( $this->row( $this->range(), $identity->public_key(), 'one_off', 'one-off' ) ),
			$store
		);

		self::assertNull( $resolver->resolve( self::EVENT_ID ) );
	}

	/** Cancelled, protected and unpublished records are suppressed at the boundary. */
	public function test_rejects_cancelled_password_protected_and_draft_events(): void {
		$identity = OccurrenceIdentity::from( self::SERIES_UID, 'one-off' );

		self::assertNull(
			$this->resolver( array( $this->row( $this->range(), $identity->public_key(), 'one_off', 'one-off', 'cancelled' ) ) )
				->resolve( self::EVENT_ID )
		);

		$this->add_event( 'publish', 'secret' );
		self::assertNull(
			$this->resolver( array( $this->row( $this->range(), $identity->public_key(), 'one_off', 'one-off' ) ) )
				->resolve( self::EVENT_ID )
		);

		$this->add_event( 'draft' );
		self::assertNull(
			$this->resolver( array( $this->row( $this->range(), $identity->public_key(), 'one_off', 'one-off' ) ) )
				->resolve( self::EVENT_ID )
		);
	}

	/** Malformed and substituted occurrence identities fail closed without series fallback. */
	public function test_rejects_invalid_or_substituted_exact_occurrence_keys(): void {
		$identity           = OccurrenceIdentity::from( self::SERIES_UID, self::TARGET );
		$presenter          = new FakeOccurrencePresentationProvider();
		$series             = ( new EventContextResolver() )->resolve_public( self::EVENT_ID );
		$presenter->context = null === $series ? null : OccurrencePresentationFixture::create( $series, $identity->public_key() );
		$resolver           = $this->resolver( array(), null, $presenter );

		self::assertNull( $resolver->resolve( self::EVENT_ID, 'NOT-A-PUBLIC-KEY' ) );
		self::assertNull( $resolver->resolve( self::EVENT_ID, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ) );
		self::assertSame(
			array(
				array(
					'event_id'   => self::EVENT_ID,
					'public_key' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
				),
			),
			$presenter->requests
		);
	}

	/** Corrupt recurrence state and substituted one-off projection rows fail closed. */
	public function test_rejects_corrupt_or_substituted_one_off_state(): void {
		$identity               = OccurrenceIdentity::from( self::SERIES_UID, 'one-off' );
		$corrupt_store          = new FakeRecurrenceAggregateStore();
		$corrupt_store->corrupt = true;

		self::assertNull(
			$this->resolver(
				array( $this->row( $this->range(), $identity->public_key(), 'one_off', 'one-off' ) ),
				$corrupt_store
			)->resolve( self::EVENT_ID )
		);

		self::assertNull(
			$this->resolver(
				array( $this->row( $this->range(), 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'one_off', 'one-off' ) )
			)->resolve( self::EVENT_ID )
		);
	}

	/**
	 * Add one canonical post with deliberately markup-bearing public content.
	 *
	 * @param string $status   WordPress post status.
	 * @param string $password Optional post password.
	 */
	private function add_event( string $status = 'publish', string $password = '' ): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'                => self::EVENT_ID,
					'post_type'         => EventPostType::POST_TYPE,
					'post_status'       => $status,
					'post_password'     => $password,
					'post_title'        => 'Calendar title',
					'post_excerpt'      => '<strong>Public</strong> excerpt [hidden]',
					'post_modified_gmt' => '2027-01-05 00:00:00',
				)
			),
			'https://example.com/events/calendar-title/'
		);
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::SERIES_UID, self::SERIES_UID );
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::STATUS, 'scheduled' );
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::VENUE, 'Main hall' );
		WordPressState::update_post_meta( self::EVENT_ID, EventMeta::ADDRESS, 'Main Street 1' );
	}

	/** Return one canonical timed occurrence range. */
	private function range(): EventDateRange {
		return EventDateRange::from_local(
			self::TARGET,
			'2027-01-05T21:00:00',
			false,
			'Europe/Brussels'
		);
	}

	/** Return a valid recurring aggregate proving the series is not one-off. */
	private function aggregate(): RecurrenceAggregate {
		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, self::TARGET, $this->range(), RecurrenceRule::daily() ) )
		);
	}

	/**
	 * Return one active public projection row.
	 *
	 * @param EventDateRange $range         Exact date range.
	 * @param string         $public_key    Stable public key.
	 * @param string         $source        Projection source.
	 * @param string         $recurrence_id Immutable recurrence identity.
	 * @param string         $status        Effective event status.
	 * @return array<string, int|string>
	 */
	private function row(
		EventDateRange $range,
		string $public_key,
		string $source,
		string $recurrence_id,
		string $status = 'scheduled'
	): array {
		return array(
			'event_id'      => self::EVENT_ID,
			'public_key'    => $public_key,
			'recurrence_id' => $recurrence_id,
			'generation'    => 7,
			'segment_id'    => 0,
			'source'        => $source,
			'start_local'   => $range->start_local(),
			'end_local'     => $range->end_local(),
			'start_utc'     => $range->start_utc(),
			'end_utc'       => $range->end_utc(),
			'timezone'      => $range->timezone(),
			'all_day'       => 0,
			'event_status'  => $status,
		);
	}

	/**
	 * Assemble the resolver around deterministic public boundaries.
	 *
	 * @param list<array<string, int|string>>         $rows      Projection rows.
	 * @param FakeRecurrenceAggregateStore|null       $store     Canonical recurrence state.
	 * @param FakeOccurrencePresentationProvider|null $presenter Exact occurrence presenter.
	 */
	private function resolver(
		array $rows,
		?FakeRecurrenceAggregateStore $store = null,
		?FakeOccurrencePresentationProvider $presenter = null
	): CalendarExportSnapshotResolver {
		$store     ??= new FakeRecurrenceAggregateStore();
		$presenter ??= new FakeOccurrencePresentationProvider();

		return new CalendarExportSnapshotResolver(
			new EventContextResolver(),
			$presenter,
			new OccurrenceReadRepository(
				new OccurrenceReadQueryBuilder(
					'wp_wpse_event_occurrences',
					'wp_posts',
					'wp_postmeta',
					'wp_term_relationships',
					'wp_term_taxonomy',
					'wp_terms'
				),
				new FakeOccurrenceReadGateway( $rows, count( $rows ) )
			),
			$store
		);
	}
}
