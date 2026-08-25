<?php
/**
 * Tests for occurrence-aware calendar feed formatting.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Calendar\CalendarEventFormatter;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionItem;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( CalendarEventFormatter::class )]
/** Proves the calendar feed uses effective occurrence identity and fields. */
final class CalendarEventFormatterOccurrenceTest extends TestCase {
	private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/** Reset deterministic taxonomy state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** A moved timed occurrence keeps its own key, URL, status, venue and wall time. */
	public function test_formats_an_effective_timed_occurrence(): void {
		$item      = $this->item(
			EventDateRange::from_local(
				'2027-03-28T19:00:00',
				'2027-03-28T21:00:00',
				false,
				'Europe/Brussels'
			)
		);
		$formatted = ( new CalendarEventFormatter() )->format_occurrence( $item );

		self::assertNotNull( $formatted );
		self::assertSame( self::KEY, $formatted['id'] );
		self::assertSame( 'Occurrence title', $formatted['title'] );
		self::assertSame( '2027-03-28T19:00:00', $formatted['start'] );
		self::assertSame( '2027-03-28T21:00:00', $formatted['end'] );
		self::assertFalse( $formatted['allDay'] );
		self::assertSame( 'postponed', $formatted['status'] );
		self::assertSame( 'https://example.com/events/series/occurrence/' . self::KEY . '/', $formatted['url'] );
		self::assertSame( 'Occurrence venue', $formatted['extendedProps']['venue'] );
		self::assertSame( 'Europe/Brussels', $formatted['extendedProps']['timezone'] );
		self::assertSame( '2027-03-28T19:00:00+02:00', $formatted['extendedProps']['startInstant'] );
	}

	/**
	 * Build one internally consistent occurrence collection item.
	 *
	 * @param EventDateRange $range Effective occurrence date range.
	 */
	private function item( EventDateRange $range ): OccurrenceCollectionItem {
		$event        = new WP_Post(
			array(
				'ID'            => 42,
				'post_type'     => EventPostType::POST_TYPE,
				'post_status'   => 'publish',
				'post_password' => '',
				'post_title'    => 'Series title',
			)
		);
		$occurrence   = OccurrenceReadModel::from_row(
			array(
				'event_id'      => 42,
				'public_key'    => self::KEY,
				'recurrence_id' => '2027-03-28T18:00:00',
				'generation'    => 8,
				'segment_id'    => 0,
				'source'        => 'rule',
				'start_local'   => $range->start_local(),
				'end_local'     => $range->end_local(),
				'start_utc'     => $range->start_utc(),
				'end_utc'       => $range->end_utc(),
				'timezone'      => $range->timezone(),
				'all_day'       => $range->all_day() ? 1 : 0,
				'event_status'  => 'postponed',
			)
		);
		$presentation = new EventPresentation(
			$event,
			'Occurrence title',
			'https://example.com/events/series/occurrence/' . self::KEY . '/',
			false,
			null,
			EventStatus::POSTPONED,
			'Occurrence venue',
			'',
			'',
			'',
			'',
			array(),
			array()
		);

		return new OccurrenceCollectionItem( $occurrence, $presentation );
	}
}
