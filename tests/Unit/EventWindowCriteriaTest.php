<?php
/**
 * Tests for bounded calendar overlap criteria.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\CalendarWindow;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies feed pagination and taxonomy bounds before WordPress is queried.
 */
#[CoversClass( EventWindowCriteria::class )]
final class EventWindowCriteriaTest extends TestCase {
	/**
	 * Maximum supported boundaries remain finite and immutable.
	 */
	public function test_supported_boundaries_are_accepted(): void {
		$criteria = new EventWindowCriteria(
			new CalendarWindow( '2027-01-15', '2027-01-16' ),
			EventWindowCriteria::MAX_LIMIT,
			EventWindowCriteria::MAX_PAGE,
			array( 'workshops' ),
			array( 'featured' )
		);

		self::assertSame( EventWindowCriteria::MAX_LIMIT, $criteria->limit );
		self::assertSame( EventWindowCriteria::MAX_PAGE, $criteria->page );
		self::assertSame( array( 'workshops' ), $criteria->category_slugs );
	}

	/**
	 * A feed query cannot become unbounded.
	 */
	public function test_limit_above_maximum_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new EventWindowCriteria(
			new CalendarWindow( '2027-01-15', '2027-01-16' ),
			EventWindowCriteria::MAX_LIMIT + 1,
			1,
			array(),
			array()
		);
	}

	/**
	 * Filter breadth remains bounded independently from result count.
	 */
	public function test_more_than_twenty_slugs_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new EventWindowCriteria(
			new CalendarWindow( '2027-01-15', '2027-01-16' ),
			50,
			1,
			array_fill( 0, 21, 'term' ),
			array()
		);
	}
}
