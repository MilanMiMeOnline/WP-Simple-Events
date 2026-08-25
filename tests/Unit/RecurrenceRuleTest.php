<?php
/**
 * Tests for validated recurrence rules.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\MonthlyRecurrenceMode;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFrequency;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Prevents unsupported or ambiguous rule shapes from reaching the engine.
 */
#[CoversClass( RecurrenceRule::class )]
final class RecurrenceRuleTest extends TestCase {
	/**
	 * Weekly weekdays are sorted but never silently deduplicated.
	 */
	public function test_weekly_rule_sorts_unique_weekdays(): void {
		$rule = RecurrenceRule::weekly( array( 7, 2, 4 ), 2 );

		self::assertSame( RecurrenceFrequency::WEEKLY, $rule->frequency() );
		self::assertSame( array( 2, 4, 7 ), $rule->weekdays() );
		self::assertSame( 2, $rule->interval() );
	}

	/**
	 * Duplicate weekdays are rejected as malformed aggregate input.
	 */
	public function test_duplicate_weekdays_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceRule::weekly( array( 1, 1 ) );
	}

	/**
	 * Monthly and yearly factories expose only their relevant fields.
	 */
	public function test_monthly_and_yearly_variants_are_explicit(): void {
		$calendar = RecurrenceRule::monthly_on_day( 31 );
		$ordinal  = RecurrenceRule::monthly_on_ordinal_weekday( -1, 5 );
		$yearly   = RecurrenceRule::yearly_on( 2, 29 );

		self::assertSame( MonthlyRecurrenceMode::DAY_OF_MONTH, $calendar->monthly_mode() );
		self::assertSame( 31, $calendar->month_day() );
		self::assertSame( MonthlyRecurrenceMode::ORDINAL_WEEKDAY, $ordinal->monthly_mode() );
		self::assertSame( -1, $ordinal->ordinal() );
		self::assertSame( 5, $ordinal->weekday() );
		self::assertSame( 2, $yearly->month() );
		self::assertSame( 29, $yearly->month_day() );
	}

	/**
	 * Unsupported intervals and impossible rule values fail closed.
	 */
	public function test_invalid_rule_values_are_rejected(): void {
		foreach (
			array(
				static fn (): RecurrenceRule => RecurrenceRule::daily( 0 ),
				static fn (): RecurrenceRule => RecurrenceRule::weekly( array() ),
				static fn (): RecurrenceRule => RecurrenceRule::weekly( array_fill( 0, 8, 1 ) ),
				static fn (): RecurrenceRule => RecurrenceRule::weekly( array( 0 ) ),
				static fn (): RecurrenceRule => RecurrenceRule::monthly_on_day( 32 ),
				static fn (): RecurrenceRule => RecurrenceRule::monthly_on_ordinal_weekday( 0, 1 ),
				static fn (): RecurrenceRule => RecurrenceRule::yearly_on( 2, 30 ),
			) as $factory
		) {
			try {
				$factory();
				self::fail( 'The malformed recurrence rule was accepted.' );
			} catch ( InvalidArgumentException ) {
				self::addToAssertionCount( 1 );
			}
		}
	}
}
