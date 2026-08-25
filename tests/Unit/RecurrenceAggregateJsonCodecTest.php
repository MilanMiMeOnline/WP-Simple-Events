<?php
/**
 * Tests for canonical bounded recurrence aggregate JSON.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\ManualOccurrence;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateJsonCodec;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves canonical ordering, round trips and fail-closed storage sanitization.
 */
#[CoversClass( RecurrenceAggregateJsonCodec::class )]
final class RecurrenceAggregateJsonCodecTest extends TestCase {
	/**
	 * Equivalent unordered exception input has one stable stored representation.
	 */
	public function test_json_round_trip_is_canonical(): void {
		$aggregate = $this->aggregate(
			array(
				$this->manual( 'manual:019c1d83-1798-4fac-a66d-ae8d67c46322', '2027-02-02' ),
				$this->manual( 'manual:019c1d83-1798-4fac-a66d-ae8d67c46320', '2027-02-01' ),
			)
		);
		$codec     = new RecurrenceAggregateJsonCodec();
		$json      = $codec->encode( $aggregate );

		self::assertStringNotContainsString( '\\/', $json );
		self::assertLessThan(
			strpos( $json, '019c1d83-1798-4fac-a66d-ae8d67c46322' ),
			strpos( $json, '019c1d83-1798-4fac-a66d-ae8d67c46320' )
		);
		self::assertSame( $json, $codec->encode( $codec->decode( $json ) ) );
	}

	/**
	 * Invalid and excessive raw metadata never becomes a partially accepted value.
	 */
	public function test_invalid_or_excessive_json_is_rejected_and_sanitized_empty(): void {
		$codec = new RecurrenceAggregateJsonCodec();

		self::assertSame( '', $codec->sanitize( '{"schema_version":1}' ) );
		self::assertSame( '', $codec->sanitize( str_repeat( 'x', RecurrenceAggregateJsonCodec::MAX_ENCODED_BYTES + 1 ) ) );

		$this->expectException( InvalidArgumentException::class );
		$codec->decode( '{broken' );
	}

	/**
	 * Return one complete aggregate with optional manual additions.
	 *
	 * @param ManualOccurrence[] $manuals Manual additions.
	 */
	private function aggregate( array $manuals = array() ): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04', null, true, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			'019c1d83-1798-4fac-a66d-ae8d67c46319',
			'Europe/Brussels',
			array( new ScheduleSegment( 0, '2027-01-04', $range, RecurrenceRule::daily() ) ),
			$manuals
		);
	}

	/**
	 * Return one manual all-day occurrence.
	 *
	 * @param string $identity Canonical manual identity.
	 * @param string $date     Canonical local date.
	 */
	private function manual( string $identity, string $date ): ManualOccurrence {
		return new ManualOccurrence(
			$identity,
			EventDateRange::from_local( $date, null, true, 'Europe/Brussels' )
		);
	}
}
