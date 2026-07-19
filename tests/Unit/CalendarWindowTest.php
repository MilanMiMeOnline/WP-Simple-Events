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
 * Verifies wall-time ISO boundaries and the public range limit.
 */
#[CoversClass( CalendarWindow::class )]
final class CalendarWindowTest extends TestCase {
	/**
	 * Visitor offsets do not alter the requested local calendar dates.
	 */
	public function test_explicit_offsets_create_an_exclusive_wall_time_window(): void {
		$window = CalendarWindow::from_iso( '2026-07-01T00:00:00+02:00', '2026-08-01T00:00:00+02:00' );

		self::assertSame( '2026-07-01', $window->start_local );
		self::assertSame( '2026-08-01', $window->end_exclusive_local );
	}

	/** Positive and negative visitor offsets describe the same wall window. */
	public function test_browser_offsets_do_not_move_the_wall_window(): void {
		$positive = CalendarWindow::from_iso( '2026-07-01T00:00:00+14:00', '2026-08-01T00:00:00+14:00' );
		$negative = CalendarWindow::from_iso( '2026-07-01T00:00:00-14:00', '2026-08-01T00:00:00-14:00' );

		self::assertEquals( $positive, $negative );
	}

	/**
	 * Ambiguous local input without a timezone is rejected.
	 */
	public function test_timezone_is_required(): void {
		$this->expectException( InvalidArgumentException::class );

		CalendarWindow::from_iso( '2026-07-01T00:00:00', '2026-08-01T00:00:00+02:00' );
	}

	/** Calendar clients must request complete local dates, not partial days. */
	public function test_non_midnight_boundary_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		CalendarWindow::from_iso( '2026-07-01T00:00:01+02:00', '2026-08-01T00:00:00+02:00' );
	}

	/** Offsets outside WordPress's supported range are rejected. */
	public function test_offset_beyond_fourteen_hours_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		CalendarWindow::from_iso( '2026-07-01T00:00:00+14:01', '2026-08-01T00:00:00+14:01' );
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

		CalendarWindow::from_iso( '2026-01-01T00:00:00Z', '2027-02-06T00:00:00Z' );
	}
}
