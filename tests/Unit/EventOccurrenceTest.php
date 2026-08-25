<?php
/**
 * Tests for projected occurrence rows.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Ensures only complete bounded projection rows can enter storage.
 */
#[CoversClass( EventOccurrence::class )]
final class EventOccurrenceTest extends TestCase {
	/**
	 * Projection rows retain local wall time and comparable UTC boundaries.
	 */
	public function test_projection_row_contains_complete_occurrence_values(): void {
		$occurrence = new EventOccurrence(
			42,
			OccurrenceIdentity::from( 'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad', '2026-09-04T20:00:00' ),
			3,
			1,
			OccurrenceSource::RULE,
			EventDateRange::from_local( '2026-09-04T20:00', '2026-09-04T22:00', false, 'Europe/Brussels' ),
			EventStatus::SCHEDULED
		);

		$row = $occurrence->projection_row();

		self::assertSame( 42, $row['event_id'] );
		self::assertSame( 3, $row['generation'] );
		self::assertSame( 'rule', $row['source'] );
		self::assertSame( '2026-09-04T20:00:00', $row['start_local'] );
		self::assertSame( 'Europe/Brussels', $row['timezone'] );
		self::assertSame( 0, $row['all_day'] );
	}

	/**
	 * Non-positive generations cannot become active projections.
	 */
	public function test_non_positive_generation_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new EventOccurrence(
			42,
			OccurrenceIdentity::from( 'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad', 'one-off' ),
			0,
			0,
			OccurrenceSource::ONE_OFF,
			EventDateRange::from_local( '2026-09-04', null, true, 'UTC' ),
			EventStatus::SCHEDULED
		);
	}
}
