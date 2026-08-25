<?php
/**
 * Tests for occurrence coverage metadata queries.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceCoverageMetaQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Protects exact gap and buffered-renewal query boundaries. */
#[CoversClass( OccurrenceCoverageMetaQuery::class )]
final class OccurrenceCoverageMetaQueryTest extends TestCase {
	/** Public gaps include one-offs while window checks remain recurring-only. */
	public function test_public_gap_query_uses_minimum_horizon(): void {
		$query = ( new OccurrenceCoverageMetaQuery() )->public_gaps( '2027-01-01' );

		self::assertSame( EventMeta::START_LOCAL, $query[0]['key'] );
		self::assertSame( EventMeta::ACTIVE_GENERATION, $query[1][0]['key'] );
		self::assertSame( EventMeta::INDEX_DIRTY, $query[1][1]['key'] );
		self::assertSame( EventMeta::RECURRENCE, $query[1][2][0]['key'] );
		self::assertSame( EventMeta::COVERAGE_FROM, $query[1][2][1][0]['key'] );
		self::assertSame( '2028-01-01', $query[1][2][1][3]['value'] );
		self::assertSame( EventMeta::COVERAGE_GENERATION, $query[1][2][1][4]['key'] );
		self::assertSame( EventMeta::COVERAGE_GENERATION, $query[1][2][1][5]['key'] );
		self::assertSame( '<=', $query[1][2][1][5]['compare'] );
		self::assertSame( 'NUMERIC', $query[1][2][1][5]['type'] );
	}

	/** Renewal candidates are recurring-only and use the earlier buffer. */
	public function test_renewal_query_uses_buffered_horizon(): void {
		$query = ( new OccurrenceCoverageMetaQuery() )->renewal_due( '2027-01-01' );

		self::assertSame( EventMeta::RECURRENCE, $query[1][0]['key'] );
		self::assertSame( EventMeta::ACTIVE_GENERATION, $query[1][1][0]['key'] );
		self::assertSame( '2028-03-26', $query[1][1][2][1][3]['value'] );
		self::assertSame( 'DATE', $query[1][1][2][1][3]['type'] );
	}
}
