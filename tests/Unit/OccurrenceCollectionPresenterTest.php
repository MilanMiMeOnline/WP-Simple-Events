<?php
/**
 * Tests for complete occurrence collection presentation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventListView;
use MiMe\WPSimpleEvents\Frontend\EventCardOptions;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventListRenderer;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionItem;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPage;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPresenter;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Occurrence\OccurrencePage;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Tests\Support\FakeProjectedOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( OccurrenceCollectionPresenter::class )]
#[CoversClass( OccurrenceCollectionPage::class )]
#[CoversClass( OccurrenceCollectionItem::class )]
#[CoversClass( EventContextResolver::class )]
/** Proves occurrence identity, cardinality and inherited fields survive presentation. */
final class OccurrenceCollectionPresenterTest extends TestCase {
	private const RECURRING_KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/** Reset deterministic public event state. */
	protected function setUp(): void {
		WordPressState::reset();
		$this->add_event( 41, 'One-off', 'https://example.com/events/one-off/' );
		$this->add_event( 42, 'Series', 'https://example.com/events/series/' );
	}

	/** One-off and recurring rows retain exact order, totals and destinations. */
	public function test_presents_a_complete_mixed_occurrence_page(): void {
		$one_off   = $this->occurrence( 41, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'one-off', 'one_off', 7 );
		$recurring = $this->occurrence( 42, self::RECURRING_KEY, '2027-01-06T19:00:00', 'rule', 8 );
		$series    = ( new EventContextResolver() )->resolve_public( 42 );

		self::assertNotNull( $series );

		$provider                                  = new FakeProjectedOccurrencePresentationProvider();
		$provider->contexts[ self::RECURRING_KEY ] = new OccurrencePresentationContext(
			$series,
			$recurring,
			'Occurrence title',
			'Occurrence note',
			0,
			'Occurrence venue',
			'',
			'',
			'',
			''
		);
		$page                                      = ( new OccurrenceCollectionPresenter( recurring: $provider ) )->present(
			new OccurrencePage( array( $one_off, $recurring ), 12, 6 )
		);

		self::assertNotNull( $page );
		self::assertSame( 12, $page->total );
		self::assertSame( 6, $page->total_pages );
		self::assertCount( 2, $page->items );
		self::assertSame( $one_off, $page->items[0]->occurrence );
		self::assertSame( 'One-off', $page->items[0]->presentation->title );
		self::assertSame( 'https://example.com/events/one-off/', $page->items[0]->presentation->permalink );
		self::assertSame( $recurring, $page->items[1]->occurrence );
		self::assertSame( 'Occurrence title', $page->items[1]->presentation->title );
		self::assertSame( 'Occurrence note', $page->items[1]->presentation->note );
		self::assertSame(
			'https://example.com/events/series/occurrence/' . self::RECURRING_KEY . '/',
			$page->items[1]->presentation->permalink
		);
		self::assertSame( array( $recurring ), $provider->requests );
		self::assertSame(
			array(
				array(
					'type' => 'post',
					'ids'  => array( 41, 42 ),
				),
			),
			WordPressState::meta_cache_calls()
		);
		self::assertSame(
			array(
				array(
					'ids'       => array( 41, 42 ),
					'post_type' => EventPostType::POST_TYPE,
				),
			),
			WordPressState::object_term_cache_calls()
		);

		$output = ( new EventListRenderer() )->render_occurrences(
			$page,
			EventListView::LIST,
			1,
			new EventCardOptions( false, false, true, true, true, 30, 'h3' ),
			'occurrence-results'
		);

		self::assertStringContainsString( 'Occurrence title', $output );
		self::assertStringContainsString( 'Occurrence venue', $output );
		self::assertStringContainsString(
			'id="wpse-event-42-occurrence-results-' . self::RECURRING_KEY . '-title"',
			$output
		);
		self::assertStringContainsString( 'https://example.com/events/series/occurrence/' . self::RECURRING_KEY . '/', $output );
	}

	/** A missing or substituted row rejects the complete page instead of skewing totals. */
	public function test_fails_closed_without_partial_collection_output(): void {
		$recurring = $this->occurrence( 42, self::RECURRING_KEY, '2027-01-06T19:00:00', 'rule', 8 );
		$provider  = new FakeProjectedOccurrencePresentationProvider();

		self::assertNull(
			( new OccurrenceCollectionPresenter( recurring: $provider ) )->present(
				new OccurrencePage( array( $recurring ), 1, 1 )
			)
		);

		$series = ( new EventContextResolver() )->resolve_public( 42 );
		self::assertNotNull( $series );
		$other                                     = $this->occurrence( 42, 'cccccccccccccccccccccccccccccccc', '2027-01-07T19:00:00', 'rule', 8 );
		$provider->contexts[ self::RECURRING_KEY ] = new OccurrencePresentationContext(
			$series,
			$other,
			'Wrong row',
			'',
			0,
			'',
			'',
			'',
			'',
			''
		);

		self::assertNull(
			( new OccurrenceCollectionPresenter( recurring: $provider ) )->present(
				new OccurrencePage( array( $recurring ), 1, 1 )
			)
		);
	}

	/**
	 * Add one public event with normalized inherited fields.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $title    Public event title.
	 * @param string $permalink Public event permalink.
	 */
	private function add_event( int $event_id, string $title, string $permalink ): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => $event_id,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => '',
					'post_title'    => $title,
				)
			),
			$permalink
		);
		WordPressState::update_post_meta( $event_id, EventMeta::STATUS, 'scheduled' );
		WordPressState::update_post_meta( $event_id, EventMeta::VENUE, 'Inherited venue' );
	}

	/**
	 * Build one strict projection row.
	 *
	 * @param int    $event_id      Canonical event post ID.
	 * @param string $public_key    Stable public occurrence key.
	 * @param string $recurrence_id Immutable recurrence identity.
	 * @param string $source        Occurrence source value.
	 * @param int    $generation    Active projection generation.
	 */
	private function occurrence(
		int $event_id,
		string $public_key,
		string $recurrence_id,
		string $source,
		int $generation
	): OccurrenceReadModel {
		$day   = 'one-off' === $recurrence_id ? '05' : substr( $recurrence_id, 8, 2 );
		$range = EventDateRange::from_local(
			'2027-01-' . $day . 'T19:00:00',
			'2027-01-' . $day . 'T21:00:00',
			false,
			'Europe/Brussels'
		);

		return OccurrenceReadModel::from_row(
			array(
				'event_id'      => $event_id,
				'public_key'    => $public_key,
				'recurrence_id' => $recurrence_id,
				'generation'    => $generation,
				'segment_id'    => 0,
				'source'        => $source,
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
}
