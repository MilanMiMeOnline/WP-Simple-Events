<?php
/**
 * Tests for canonical recurrence definition storage shapes.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceDefinition;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceDefinitionCodec;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEnd;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\SpecificDatesSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves round-trip stability, strict variants and unknown-field rejection.
 */
#[CoversClass( RecurrenceDefinitionCodec::class )]
final class RecurrenceDefinitionCodecTest extends TestCase {
	/**
	 * Every supported rule variant has one stable canonical representation.
	 *
	 * @param RecurrenceDefinition $definition Validated definition.
	 * @param array<string, mixed> $expected Canonical array shape.
	 */
	#[DataProvider( 'definition_provider' )]
	public function test_supported_definitions_round_trip( RecurrenceDefinition $definition, array $expected ): void {
		$codec = new RecurrenceDefinitionCodec();

		self::assertSame( $expected, $codec->encode( $definition ) );
		self::assertSame( $expected, $codec->encode( $codec->decode( $expected ) ) );
	}

	/**
	 * Return all supported frequencies, modes and end variants.
	 *
	 * @return iterable<string, array{0: RecurrenceDefinition, 1: array<string, mixed>}>
	 */
	public static function definition_provider(): iterable {
		yield 'daily never' => array(
			RecurrenceRule::daily( 2 ),
			array(
				'type'      => 'rule',
				'frequency' => 'daily',
				'interval'  => 2,
				'end'       => array( 'mode' => 'never' ),
			),
		);

		yield 'weekly until' => array(
			RecurrenceRule::weekly( array( 1, 3, 5 ), 1, RecurrenceEnd::on( '2027-12-31' ) ),
			array(
				'type'      => 'rule',
				'frequency' => 'weekly',
				'interval'  => 1,
				'end'       => array(
					'mode' => 'until',
					'date' => '2027-12-31',
				),
				'weekdays'  => array( 1, 3, 5 ),
			),
		);

		yield 'monthly day count' => array(
			RecurrenceRule::monthly_on_day( 31, 2, RecurrenceEnd::after( 8 ) ),
			array(
				'type'         => 'rule',
				'frequency'    => 'monthly',
				'interval'     => 2,
				'end'          => array(
					'mode'  => 'count',
					'count' => 8,
				),
				'monthly_mode' => 'day_of_month',
				'month_day'    => 31,
			),
		);

		yield 'monthly last weekday' => array(
			RecurrenceRule::monthly_on_ordinal_weekday( -1, 7 ),
			array(
				'type'         => 'rule',
				'frequency'    => 'monthly',
				'interval'     => 1,
				'end'          => array( 'mode' => 'never' ),
				'monthly_mode' => 'ordinal_weekday',
				'ordinal'      => -1,
				'weekday'      => 7,
			),
		);

		yield 'yearly leap day' => array(
			RecurrenceRule::yearly_on( 2, 29, 4 ),
			array(
				'type'      => 'rule',
				'frequency' => 'yearly',
				'interval'  => 4,
				'end'       => array( 'mode' => 'never' ),
				'month'     => 2,
				'month_day' => 29,
			),
		);

		yield 'specific dates' => array(
			SpecificDatesSchedule::from_dates( array( '2027-05-02', '2027-05-01' ) ),
			array(
				'type'  => 'specific_dates',
				'dates' => array( '2027-05-01', '2027-05-02' ),
			),
		);
	}

	/**
	 * Invalid or non-canonical data fails as a complete definition.
	 *
	 * @param mixed $value Untrusted storage value.
	 */
	#[DataProvider( 'invalid_value_provider' )]
	public function test_invalid_storage_values_are_rejected( mixed $value ): void {
		$this->expectException( InvalidArgumentException::class );

		( new RecurrenceDefinitionCodec() )->decode( $value );
	}

	/**
	 * Return representative structural and domain-invalid values.
	 *
	 * @return iterable<string, array{0: mixed}>
	 */
	public static function invalid_value_provider(): iterable {
		yield 'not an object shape' => array( array( 'rule' ) );
		yield 'unknown type' => array(
			array(
				'type'  => 'rrule',
				'value' => 'FREQ=DAILY',
			),
		);
		yield 'unknown daily field' => array(
			array(
				'type'      => 'rule',
				'frequency' => 'daily',
				'interval'  => 1,
				'end'       => array( 'mode' => 'never' ),
				'weekdays'  => array( 1 ),
			),
		);
		yield 'numeric string interval' => array(
			array(
				'type'      => 'rule',
				'frequency' => 'daily',
				'interval'  => '1',
				'end'       => array( 'mode' => 'never' ),
			),
		);
		yield 'unknown end field' => array(
			array(
				'type'      => 'rule',
				'frequency' => 'daily',
				'interval'  => 1,
				'end'       => array(
					'mode' => 'never',
					'date' => '2027-01-01',
				),
			),
		);
		yield 'missing weekly weekdays' => array(
			array(
				'type'      => 'rule',
				'frequency' => 'weekly',
				'interval'  => 1,
				'end'       => array( 'mode' => 'never' ),
			),
		);
		yield 'invalid monthly mode' => array(
			array(
				'type'         => 'rule',
				'frequency'    => 'monthly',
				'interval'     => 1,
				'end'          => array( 'mode' => 'never' ),
				'monthly_mode' => 'nearest_weekday',
			),
		);
		yield 'duplicate specific dates' => array(
			array(
				'type'  => 'specific_dates',
				'dates' => array( '2027-01-01', '2027-01-01' ),
			),
		);
	}

	/**
	 * Unknown implementations cannot cross the persistence boundary.
	 */
	public function test_unknown_definition_implementation_cannot_be_encoded(): void {
		$this->expectException( InvalidArgumentException::class );

		( new RecurrenceDefinitionCodec() )->encode( new class() implements RecurrenceDefinition {} );
	}
}
