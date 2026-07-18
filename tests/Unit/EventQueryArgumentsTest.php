<?php
/**
 * Tests for public event WP_Query arguments.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Domain\CalendarWindow;
use MiMe\WPSimpleEvents\Query\EventQueryArguments;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies visibility, active boundaries, ordering and taxonomy isolation.
 */
#[CoversClass( EventQueryArguments::class )]
final class EventQueryArgumentsTest extends TestCase {
	/**
	 * Upcoming includes an event whose inclusive end equals now.
	 */
	public function test_upcoming_query_includes_active_boundary_and_orders_by_start(): void {
		$arguments = ( new EventQueryArguments() )->build(
			new EventQueryCriteria( EventPeriod::UPCOMING, 12, 2, array(), array(), 1_800_000_000 )
		);

		self::assertSame( EventPostType::POST_TYPE, $arguments['post_type'] );
		self::assertSame( 'publish', $arguments['post_status'] );
		self::assertFalse( $arguments['has_password'] );
		self::assertSame( EventMeta::START_UTC, $arguments['meta_key'] );
		self::assertSame( 'ASC', $arguments['order'] );
		self::assertSame( 12, $arguments['posts_per_page'] );
		self::assertSame( 2, $arguments['paged'] );
		self::assertSame( EventMeta::END_UTC, $arguments['meta_query'][0]['key'] );
		self::assertSame( '>=', $arguments['meta_query'][0]['compare'] );
		self::assertSame( 1_800_000_000, $arguments['meta_query'][0]['value'] );
	}

	/**
	 * Past excludes the active boundary and sorts newest starts first.
	 */
	public function test_past_query_uses_strict_end_boundary_and_descending_order(): void {
		$arguments = ( new EventQueryArguments() )->build(
			new EventQueryCriteria( EventPeriod::PAST, 10, 1, array(), array(), 1_800_000_000 )
		);

		self::assertSame( '<', $arguments['meta_query'][0]['compare'] );
		self::assertSame( 'DESC', $arguments['order'] );
	}

	/**
	 * All period omits the time boundary while retaining valid start ordering.
	 */
	public function test_all_query_has_no_period_meta_query(): void {
		$arguments = ( new EventQueryArguments() )->build(
			new EventQueryCriteria( EventPeriod::ALL, 10, 1, array(), array(), 1_800_000_000 )
		);

		self::assertArrayNotHasKey( 'meta_query', $arguments );
		self::assertSame( 'ASC', $arguments['order'] );
	}

	/**
	 * Category and tag filters use separate event taxonomies with AND semantics.
	 */
	public function test_category_and_tag_filters_are_isolated_to_event_taxonomies(): void {
		$arguments = ( new EventQueryArguments() )->build(
			new EventQueryCriteria(
				EventPeriod::UPCOMING,
				10,
				1,
				array( 'workshops', 'talks' ),
				array( 'featured' ),
				1_800_000_000
			)
		);

		self::assertSame( 'AND', $arguments['tax_query']['relation'] );
		self::assertSame( EventTaxonomies::CATEGORY, $arguments['tax_query'][0]['taxonomy'] );
		self::assertSame( array( 'workshops', 'talks' ), $arguments['tax_query'][0]['terms'] );
		self::assertSame( EventTaxonomies::TAG, $arguments['tax_query'][1]['taxonomy'] );
	}

	/**
	 * Calendar queries use an inclusive event end and exclusive request end.
	 */
	public function test_window_query_uses_the_documented_overlap_boundaries(): void {
		$arguments = ( new EventQueryArguments() )->build_window(
			new EventWindowCriteria(
				new CalendarWindow( 1_800_000_000, 1_802_678_400 ),
				100,
				2,
				array( 'workshops' ),
				array()
			)
		);

		self::assertSame( 'publish', $arguments['post_status'] );
		self::assertFalse( $arguments['has_password'] );
		self::assertSame( 'AND', $arguments['meta_query']['relation'] );
		self::assertSame( EventMeta::END_UTC, $arguments['meta_query'][0]['key'] );
		self::assertSame( '>=', $arguments['meta_query'][0]['compare'] );
		self::assertSame( 1_800_000_000, $arguments['meta_query'][0]['value'] );
		self::assertSame( EventMeta::START_UTC, $arguments['meta_query'][1]['key'] );
		self::assertSame( '<', $arguments['meta_query'][1]['compare'] );
		self::assertSame( 1_802_678_400, $arguments['meta_query'][1]['value'] );
		self::assertSame( 100, $arguments['posts_per_page'] );
		self::assertSame( 2, $arguments['paged'] );
	}
}
