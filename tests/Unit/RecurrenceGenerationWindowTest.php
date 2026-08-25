<?php
/**
 * Tests for bounded recurrence windows.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ensures callers cannot request unbounded expansion.
 */
#[CoversClass( RecurrenceGenerationWindow::class )]
final class RecurrenceGenerationWindowTest extends TestCase {
	/**
	 * Valid inclusive bounds and row cap remain observable.
	 */
	public function test_valid_window_is_retained(): void {
		$window = RecurrenceGenerationWindow::between( '2026-08-20', '2027-02-20', 400 );

		self::assertSame( '2026-08-20', $window->from_date() );
		self::assertSame( '2027-02-20', $window->through_date() );
		self::assertSame( 400, $window->max_rows() );
	}

	/**
	 * Invalid or excessive requests are rejected before expansion.
	 *
	 * @param string $from    Candidate inclusive start.
	 * @param string $through Candidate inclusive end.
	 * @param int    $rows    Candidate row limit.
	 */
	#[DataProvider( 'invalid_windows' )]
	public function test_invalid_window_is_rejected( string $from, string $through, int $rows ): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceGenerationWindow::between( $from, $through, $rows );
	}

	/**
	 * Provide invalid generation windows.
	 *
	 * @return iterable<string, array{string, string, int}>
	 */
	public static function invalid_windows(): iterable {
		yield 'invalid date' => array( '2026-02-30', '2026-03-01', 10 );
		yield 'reversed' => array( '2026-09-01', '2026-08-01', 10 );
		yield 'excessive horizon' => array( '2026-01-01', '2027-07-06', 10 );
		yield 'zero rows' => array( '2026-01-01', '2026-02-01', 0 );
		yield 'excessive rows' => array( '2026-01-01', '2026-02-01', RecurrenceGenerationWindow::MAX_ROWS + 1 );
	}
}
