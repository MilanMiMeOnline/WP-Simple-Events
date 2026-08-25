<?php
/**
 * Tests for occurrence SQL query planning.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\CalendarWindow;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadQueryBuilder;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves visibility, active-generation, overlap, taxonomy and paging semantics.
 */
#[CoversClass( OccurrenceReadQueryBuilder::class )]
final class OccurrenceReadQueryBuilderTest extends TestCase {
	/**
	 * Public period plans recheck the parent and keep exact occurrence pagination.
	 */
	public function test_upcoming_plan_is_permission_aware_bounded_and_taxonomy_canonical(): void {
		$plan = $this->builder()->build(
			new EventQueryCriteria(
				EventPeriod::UPCOMING,
				12,
				3,
				array( 'workshops', 'talks' ),
				array( 'featured' ),
				1_800_000_000
			)
		);

		self::assertStringContainsString( 'INNER JOIN wp_posts p ON p.ID = o.event_id', $plan->rows->sql );
		self::assertStringContainsString( 'p.post_status = %s', $plan->rows->sql );
		self::assertStringContainsString( "p.post_password = ''", $plan->rows->sql );
		self::assertStringContainsString( 'CAST(ag.meta_value AS UNSIGNED) = o.generation', $plan->rows->sql );
		self::assertStringContainsString( 'CAST(cg.meta_value AS UNSIGNED) = o.generation', $plan->rows->sql );
		self::assertStringContainsString( 'NOT EXISTS (SELECT 1 FROM wp_postmeta dg', $plan->rows->sql );
		self::assertStringContainsString( 'o.end_utc >= %d', $plan->rows->sql );
		self::assertStringContainsString( 'o.event_status <> %s', $plan->rows->sql );
		self::assertSame( 2, substr_count( $plan->rows->sql, 'EXISTS (SELECT 1 FROM wp_term_relationships' ) );
		self::assertStringContainsString( 'ORDER BY o.start_utc ASC, o.event_id ASC, o.public_key ASC', $plan->rows->sql );
		self::assertSame(
			array(
				EventPostType::POST_TYPE,
				'publish',
				EventMeta::ACTIVE_GENERATION,
				'one_off',
				EventMeta::COVERAGE_GENERATION,
				EventMeta::INDEX_DIRTY,
				1_800_000_000,
				EventStatus::CANCELLED->value,
				EventTaxonomies::CATEGORY,
				'workshops',
				'talks',
				EventTaxonomies::TAG,
				'featured',
				12,
				24,
			),
			$plan->rows->parameters
		);
		self::assertSame( array_slice( $plan->rows->parameters, 0, -2 ), $plan->count->parameters );
		self::assertStringContainsString( 'SELECT COUNT(*)', $plan->count->sql );
	}

	/**
	 * Past plans use the strict active boundary and deterministic reverse order.
	 */
	public function test_past_plan_is_strict_and_descending(): void {
		$plan = $this->builder()->build(
			new EventQueryCriteria( EventPeriod::PAST, 10, 1, array(), array(), 1_800_000_000 )
		);

		self::assertStringContainsString( 'o.end_utc < %d', $plan->rows->sql );
		self::assertStringNotContainsString( 'o.event_status <> %s', $plan->rows->sql );
		self::assertStringContainsString( 'ORDER BY o.start_utc DESC, o.event_id ASC, o.public_key ASC', $plan->rows->sql );
	}

	/**
	 * Calendar plans preserve the existing inclusive-end and exclusive-end window.
	 */
	public function test_window_plan_uses_local_overlap_and_exact_offset(): void {
		$plan = $this->builder()->build_window(
			new EventWindowCriteria(
				new CalendarWindow( '2027-01-15', '2027-02-15' ),
				100,
				2,
				array(),
				array()
			)
		);

		self::assertStringContainsString( 'o.end_local >= %s', $plan->rows->sql );
		self::assertStringContainsString( 'o.start_local < %s', $plan->rows->sql );
		self::assertStringNotContainsString( 'o.event_status <> %s', $plan->rows->sql );
		self::assertStringContainsString( 'ORDER BY o.start_local ASC', $plan->rows->sql );
		self::assertSame( array( '2027-01-15', '2027-02-15', 100, 100 ), array_slice( $plan->rows->parameters, -4 ) );
	}

	/** Exact public identity plans remain bounded and recheck parent visibility. */
	public function test_public_identity_plan_is_exact_bounded_and_permission_aware(): void {
		$plan = $this->builder()->build_public_identity( 42, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' );

		self::assertStringContainsString( 'INNER JOIN wp_posts p ON p.ID = o.event_id', $plan->sql );
		self::assertStringContainsString( 'p.post_status = %s', $plan->sql );
		self::assertStringContainsString( "p.post_password = ''", $plan->sql );
		self::assertStringContainsString( 'CAST(ag.meta_value AS UNSIGNED) = o.generation', $plan->sql );
		self::assertStringContainsString( 'CAST(cg.meta_value AS UNSIGNED) = o.generation', $plan->sql );
		self::assertStringContainsString( 'NOT EXISTS (SELECT 1 FROM wp_postmeta dg', $plan->sql );
		self::assertStringContainsString( 'o.event_id = %d', $plan->sql );
		self::assertStringContainsString( 'o.public_key = %s', $plan->sql );
		self::assertStringEndsWith( 'LIMIT 2', $plan->sql );
		self::assertSame(
			array(
				EventPostType::POST_TYPE,
				'publish',
				EventMeta::ACTIVE_GENERATION,
				'one_off',
				EventMeta::COVERAGE_GENERATION,
				EventMeta::INDEX_DIRTY,
				42,
				'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			),
			$plan->parameters
		);
	}

	/** Malformed public identity input never reaches a SQL plan. */
	public function test_public_identity_plan_rejects_malformed_keys(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->builder()->build_public_identity( 42, 'AAAA-not-a-public-key' );
	}

	/** Sitemap plans exclude one-off rows and retain the hard page ceiling. */
	public function test_sitemap_plan_is_recurring_only_and_bounded(): void {
		$plan = $this->builder()->build_sitemap( 100, 3 );

		self::assertStringContainsString( 'o.source <> %s', $plan->rows->sql );
		self::assertStringContainsString( 'ORDER BY o.start_utc ASC', $plan->rows->sql );
		self::assertSame( 'one_off', $plan->rows->parameters[6] );
		self::assertSame( array( 100, 200 ), array_slice( $plan->rows->parameters, -2 ) );
		self::assertSame( array_slice( $plan->rows->parameters, 0, -2 ), $plan->count->parameters );
	}

	/** Sitemap counts repeat the same public-parent and recurrence predicates. */
	public function test_sitemap_count_matches_candidate_visibility(): void {
		$query = $this->builder()->build_sitemap_count( 75 );

		self::assertStringContainsString( 'SELECT COUNT(*)', $query->sql );
		self::assertStringContainsString( 'p.post_status = %s', $query->sql );
		self::assertStringContainsString( 'o.source <> %s', $query->sql );
		self::assertSame( 'one_off', $query->parameters[6] );
	}

	/** Unsafe sitemap page sizes cannot be expanded through a Core filter. */
	public function test_sitemap_plan_rejects_unbounded_pagination(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->builder()->build_sitemap( 101, 1 );
	}

	/**
	 * Dynamic SQL identifiers are accepted only from a strict internal allowlist shape.
	 */
	public function test_invalid_table_identifier_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new OccurrenceReadQueryBuilder(
			'wp_occurrences; DROP TABLE wp_posts',
			'wp_posts',
			'wp_postmeta',
			'wp_term_relationships',
			'wp_term_taxonomy',
			'wp_terms'
		);
	}

	/**
	 * Return a deterministic table-name configuration.
	 */
	private function builder(): OccurrenceReadQueryBuilder {
		return new OccurrenceReadQueryBuilder(
			'wp_wpse_event_occurrences',
			'wp_posts',
			'wp_postmeta',
			'wp_term_relationships',
			'wp_term_taxonomy',
			'wp_terms'
		);
	}
}
