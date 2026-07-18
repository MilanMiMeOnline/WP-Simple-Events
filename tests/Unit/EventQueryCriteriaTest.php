<?php
/**
 * Tests for public event query criteria.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies public query bounds before WordPress receives any arguments.
 */
#[CoversClass( EventQueryCriteria::class )]
final class EventQueryCriteriaTest extends TestCase {
	/**
	 * Supported boundary values produce immutable criteria.
	 */
	public function test_supported_boundaries_are_accepted(): void {
		$criteria = new EventQueryCriteria(
			EventPeriod::UPCOMING,
			EventQueryCriteria::MAX_LIMIT,
			1,
			array( 'workshops' ),
			array( 'featured' ),
			1_800_000_000
		);

		self::assertSame( EventPeriod::UPCOMING, $criteria->period );
		self::assertSame( EventQueryCriteria::MAX_LIMIT, $criteria->limit );
		self::assertSame( array( 'workshops' ), $criteria->category_slugs );
	}

	/**
	 * Result limits cannot create unbounded public queries.
	 */
	public function test_limit_above_maximum_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new EventQueryCriteria( EventPeriod::ALL, 51, 1, array(), array(), 1_800_000_000 );
	}

	/**
	 * Page numbers are always one-based.
	 */
	public function test_non_positive_page_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new EventQueryCriteria( EventPeriod::PAST, 12, 0, array(), array(), 1_800_000_000 );
	}

	/**
	 * Excessive offsets are rejected to protect public query performance.
	 */
	public function test_page_above_maximum_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new EventQueryCriteria(
			EventPeriod::PAST,
			12,
			EventQueryCriteria::MAX_PAGE + 1,
			array(),
			array(),
			1_800_000_000
		);
	}

	/**
	 * Term filter breadth is bounded independently from result count.
	 */
	public function test_more_than_twenty_term_slugs_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new EventQueryCriteria(
			EventPeriod::UPCOMING,
			12,
			1,
			array_fill( 0, 21, 'term' ),
			array(),
			1_800_000_000
		);
	}
}
