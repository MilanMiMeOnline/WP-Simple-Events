<?php
/**
 * Tests for public calendar windows.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\CalendarWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies absolute ISO boundaries and the public range limit.
 */
#[CoversClass( CalendarWindow::class )]
final class CalendarWindowTest extends TestCase {
	/**
	 * Explicit offsets are converted to one absolute, exclusive interval.
	 */
	public function test_explicit_offsets_create_an_exclusive_utc_window(): void {
		$window = CalendarWindow::from_iso( '2026-07-01T00:00:00+02:00', '2026-08-01T00:00:00+02:00' );

		self::assertSame( 1_782_856_800, $window->start_utc );
		self::assertSame( 1_785_535_200, $window->end_exclusive_utc );
	}

	/**
	 * Ambiguous local input without a timezone is rejected.
	 */
	public function test_timezone_is_required(): void {
		$this->expectException( InvalidArgumentException::class );

		CalendarWindow::from_iso( '2026-07-01T00:00:00', '2026-08-01T00:00:00+02:00' );
	}

	/**
	 * The end must be strictly later because it is an exclusive boundary.
	 */
	public function test_empty_or_reversed_windows_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		CalendarWindow::from_iso( '2026-08-01T00:00:00Z', '2026-08-01T00:00:00Z' );
	}

	/**
	 * Public clients cannot request more than four hundred days at once.
	 */
	public function test_range_above_four_hundred_days_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		CalendarWindow::from_iso( '2026-01-01T00:00:00Z', '2027-02-06T00:00:01Z' );
	}
}
