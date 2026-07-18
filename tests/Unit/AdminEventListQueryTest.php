<?php
/**
 * Tests for the event admin-list query contract.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\AdminEventListQuery;
use MiMe\WPSimpleEvents\Content\EventMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminEventListQuery::class )]
/**
 * Verifies allowlisted admin views and sort keys without WordPress globals.
 */
final class AdminEventListQueryTest extends TestCase {
	/**
	 * Upcoming includes active events and sorts by ascending start.
	 */
	public function test_builds_upcoming_view_at_the_inclusive_end_boundary(): void {
		$arguments = ( new AdminEventListQuery() )->arguments( 'upcoming', '', '', 1_800_000_000 );

		self::assertSame( EventMeta::START_UTC, $arguments['meta_key'] ?? null );
		self::assertSame( 'meta_value_num', $arguments['orderby'] ?? null );
		self::assertSame( 'ASC', $arguments['order'] ?? null );
		self::assertSame(
			array(
				array(
					'key'     => EventMeta::END_UTC,
					'value'   => 1_800_000_000,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
			$arguments['meta_query'] ?? null
		);
	}

	/**
	 * Past events use the inverse boundary and descending start order.
	 */
	public function test_builds_past_view(): void {
		$arguments = ( new AdminEventListQuery() )->arguments( 'past', '', '', 1_800_000_000 );

		self::assertSame( '<', $arguments['meta_query'][0]['compare'] ?? null );
		self::assertSame( 'DESC', $arguments['order'] ?? null );
	}

	/**
	 * Exceptional event status is independent from post publication status.
	 */
	public function test_builds_exceptional_status_views(): void {
		$builder   = new AdminEventListQuery();
		$cancelled = $builder->arguments( 'cancelled', '', '', 1_800_000_000 );
		$postponed = $builder->arguments( 'postponed', '', '', 1_800_000_000 );

		self::assertSame( EventMeta::STATUS, $cancelled['meta_query'][0]['key'] ?? null );
		self::assertSame( 'cancelled', $cancelled['meta_query'][0]['value'] ?? null );
		self::assertSame( 'postponed', $postponed['meta_query'][0]['value'] ?? null );
	}

	/**
	 * Unknown views and sort keys cannot become arbitrary metadata queries.
	 */
	public function test_rejects_unknown_query_controls(): void {
		$arguments = ( new AdminEventListQuery() )->arguments( 'private', 'arbitrary_meta', 'DROP TABLE', 1_800_000_000 );

		self::assertSame( array(), $arguments );
	}

	/**
	 * The two displayed date columns map only to their numeric UTC indexes.
	 */
	public function test_builds_allowlisted_date_sorting(): void {
		$builder = new AdminEventListQuery();

		$start = $builder->arguments( 'all', 'wpse_start', 'desc', 1_800_000_000 );
		$end   = $builder->arguments( 'all', 'wpse_end', 'ASC', 1_800_000_000 );

		self::assertSame( EventMeta::START_UTC, $start['meta_key'] ?? null );
		self::assertSame( 'DESC', $start['order'] ?? null );
		self::assertSame( EventMeta::END_UTC, $end['meta_key'] ?? null );
		self::assertSame( 'ASC', $end['order'] ?? null );
	}
}
