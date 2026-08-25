<?php
/**
 * Tests for bounded inactive-generation cleanup SQL.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceGenerationCleanupQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves age, activity, dirty-state and batch boundaries in both cleanup phases.
 */
#[CoversClass( OccurrenceGenerationCleanupQueryBuilder::class )]
final class OccurrenceGenerationCleanupQueryBuilderTest extends TestCase {
	/** Candidate selection is deterministic, bounded and excludes active or dirty rows. */
	public function test_builds_bounded_candidate_selection(): void {
		$query = $this->builder()->candidates( 1_800_000_000, 100 );

		self::assertStringContainsString( 'SELECT DISTINCT o.id', $query->sql );
		self::assertStringContainsString( 'dg.post_id IS NULL', $query->sql );
		self::assertStringContainsString( 'o.generation <> CAST(ag.meta_value AS UNSIGNED)', $query->sql );
		self::assertStringContainsString( 'o.created_utc <= %d', $query->sql );
		self::assertStringEndsWith( 'ORDER BY o.id ASC LIMIT %d', $query->sql );
		self::assertSame(
			array( EventMeta::INDEX_DIRTY, EventMeta::ACTIVE_GENERATION, 1_800_000_000, 100 ),
			$query->parameters
		);
	}

	/** Guarded deletion repeats every mutable predicate for selected IDs only. */
	public function test_builds_guarded_candidate_deletion(): void {
		$query = $this->builder()->delete( array( 9, 12 ), 1_800_000_000 );

		self::assertStringContainsString( 'DELETE o FROM wp_wpse_event_occurrences o', $query->sql );
		self::assertStringContainsString( 'dg.post_id IS NULL', $query->sql );
		self::assertStringContainsString( 'o.id IN (%d, %d)', $query->sql );
		self::assertStringContainsString( 'o.generation <> CAST(ag.meta_value AS UNSIGNED)', $query->sql );
		self::assertSame(
			array( EventMeta::INDEX_DIRTY, EventMeta::ACTIVE_GENERATION, 9, 12, 1_800_000_000 ),
			$query->parameters
		);
	}

	/** Unsafe identifiers, bounds and candidate identities fail before SQL execution. */
	public function test_rejects_invalid_contracts(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->builder()->delete( array( 9, 9 ), 1_800_000_000 );
	}

	/** Batch selection cannot exceed the fixed 100-row ceiling. */
	public function test_rejects_oversized_batch(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->builder()->candidates( 1_800_000_000, 101 );
	}

	/** Return a builder with trusted table identifiers. */
	private function builder(): OccurrenceGenerationCleanupQueryBuilder {
		return new OccurrenceGenerationCleanupQueryBuilder(
			'wp_wpse_event_occurrences',
			'wp_postmeta'
		);
	}
}
