<?php
/**
 * Tests for one-off recurrence bootstrap.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Application\RecurrenceAggregateBootstrapper;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Recurrence\SpecificDatesSchedule;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves strict reuse of canonical one-off identity, timezone and date state.
 */
#[CoversClass( RecurrenceAggregateBootstrapper::class )]
final class RecurrenceAggregateBootstrapperTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/**
	 * Configure one valid timed event before each test.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);
		WordPressState::update_post_meta( 42, EventMeta::SERIES_UID, self::SERIES_UID );
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, '2027-01-04T19:00:00' );
		WordPressState::update_post_meta( 42, EventMeta::END_LOCAL, '2027-01-04T21:00:00' );
		WordPressState::update_post_meta( 42, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( 42, EventMeta::STATUS, 'scheduled' );
	}

	/**
	 * A valid one-off becomes one exact specific-date root without changing time.
	 */
	public function test_timed_event_bootstraps_one_date_aggregate(): void {
		$aggregate  = ( new RecurrenceAggregateBootstrapper() )->from_event( 42 );
		$definition = $aggregate->segments[0]->definition;

		self::assertSame( self::SERIES_UID, $aggregate->series_uid );
		self::assertSame( 'Europe/Brussels', $aggregate->timezone );
		self::assertSame( '2027-01-04T19:00:00', $aggregate->segments[0]->anchor );
		self::assertSame( '2027-01-04T21:00:00', $aggregate->segments[0]->template->end_local() );
		self::assertInstanceOf( SpecificDatesSchedule::class, $definition );
		self::assertSame( array( '2027-01-04' ), $definition->dates() );
	}

	/**
	 * Canonical all-day metadata remains inclusive and date-only.
	 */
	public function test_all_day_event_bootstraps_inclusive_range(): void {
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, '2027-01-04' );
		WordPressState::update_post_meta( 42, EventMeta::END_LOCAL, '2027-01-06' );
		WordPressState::update_post_meta( 42, EventMeta::ALL_DAY, '1' );
		$aggregate = ( new RecurrenceAggregateBootstrapper() )->from_event( 42 );

		self::assertTrue( $aggregate->segments[0]->template->all_day() );
		self::assertSame( '2027-01-06', $aggregate->segments[0]->template->end_local() );
	}

	/**
	 * Missing, malformed or structurally invalid canonical state fails closed.
	 */
	public function test_corrupt_event_state_is_rejected(): void {
		WordPressState::update_post_meta( 42, EventMeta::ALL_DAY, 'yes' );
		$this->expectException( InvalidArgumentException::class );

		( new RecurrenceAggregateBootstrapper() )->from_event( 42 );
	}

	/**
	 * Recurrence cannot be initialized for another post type.
	 */
	public function test_non_event_is_rejected(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 99,
					'post_type' => 'post',
				)
			)
		);
		$this->expectException( InvalidArgumentException::class );

		( new RecurrenceAggregateBootstrapper() )->from_event( 99 );
	}
}
