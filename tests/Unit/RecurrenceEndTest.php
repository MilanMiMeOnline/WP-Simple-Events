<?php
/**
 * Tests for recurrence termination conditions.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEnd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ensures termination values are strict, bounded and mutually exclusive.
 */
#[CoversClass( RecurrenceEnd::class )]
final class RecurrenceEndTest extends TestCase {
	/**
	 * Supported termination forms retain only their intended value.
	 */
	public function test_supported_termination_forms(): void {
		self::assertNull( RecurrenceEnd::never()->until_date() );
		self::assertNull( RecurrenceEnd::never()->count() );
		self::assertSame( '2027-04-30', RecurrenceEnd::on( '2027-04-30' )->until_date() );
		self::assertNull( RecurrenceEnd::on( '2027-04-30' )->count() );
		self::assertSame( 12, RecurrenceEnd::after( 12 )->count() );
		self::assertNull( RecurrenceEnd::after( 12 )->until_date() );
	}

	/**
	 * Invalid dates are rejected without PHP date normalization.
	 *
	 * @param string $date Invalid end-date candidate.
	 */
	#[DataProvider( 'invalid_dates' )]
	public function test_invalid_end_date_is_rejected( string $date ): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceEnd::on( $date );
	}

	/**
	 * Provide invalid end-date candidates.
	 *
	 * @return iterable<string, array{string}>
	 */
	public static function invalid_dates(): iterable {
		yield 'impossible' => array( '2027-02-29' );
		yield 'noncanonical' => array( '2027-2-01' );
		yield 'padded' => array( ' 2027-02-01 ' );
	}

	/**
	 * Count bounds are enforced at both edges.
	 *
	 * @param int $count Invalid occurrence-count candidate.
	 */
	#[DataProvider( 'invalid_counts' )]
	public function test_invalid_count_is_rejected( int $count ): void {
		$this->expectException( InvalidArgumentException::class );

		RecurrenceEnd::after( $count );
	}

	/**
	 * Provide invalid occurrence-count candidates.
	 *
	 * @return iterable<string, array{int}>
	 */
	public static function invalid_counts(): iterable {
		yield 'zero' => array( 0 );
		yield 'excessive' => array( RecurrenceEnd::MAX_COUNT + 1 );
	}
}
