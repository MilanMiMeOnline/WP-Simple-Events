<?php
/**
 * Tests for specific-dates schedules.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\SpecificDatesSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Keeps explicit schedules canonical, unique and bounded.
 */
#[CoversClass( SpecificDatesSchedule::class )]
final class SpecificDatesScheduleTest extends TestCase {
	/**
	 * Valid dates are sorted chronologically.
	 */
	public function test_dates_are_sorted(): void {
		$schedule = SpecificDatesSchedule::from_dates(
			array( '2026-11-03', '2026-09-01', '2026-10-15' )
		);

		self::assertSame(
			array( '2026-09-01', '2026-10-15', '2026-11-03' ),
			$schedule->dates()
		);
	}

	/**
	 * Empty, duplicate, malformed and excessive schedules fail closed.
	 */
	public function test_invalid_schedules_are_rejected(): void {
		$invalid = array(
			array(),
			array( '2026-09-01', '2026-09-01' ),
			array( '2026-02-30' ),
			array_fill( 0, SpecificDatesSchedule::MAX_DATES + 1, '2026-09-01' ),
		);

		foreach ( $invalid as $dates ) {
			try {
				SpecificDatesSchedule::from_dates( $dates );
				self::fail( 'The malformed specific-dates schedule was accepted.' );
			} catch ( InvalidArgumentException ) {
				self::addToAssertionCount( 1 );
			}
		}
	}
}
