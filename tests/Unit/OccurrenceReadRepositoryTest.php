<?php
/**
 * Tests for occurrence result validation and pagination.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Occurrence\OccurrencePage;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadQueryBuilder;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceReadGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Ensures recurring rows retain occurrence identity and corrupt data fails closed.
 */
#[CoversClass( OccurrenceReadRepository::class )]
#[CoversClass( OccurrenceReadModel::class )]
#[CoversClass( OccurrencePage::class )]
final class OccurrenceReadRepositoryTest extends TestCase {
	/**
	 * Two occurrences of one series remain two ordered results and two count units.
	 */
	public function test_repository_does_not_deduplicate_repeated_event_ids(): void {
		$gateway    = new FakeOccurrenceReadGateway(
			array(
				$this->row( '2027-01-01T10:00:00', '2027-01-01T11:00:00', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
				$this->row( '2027-01-08T10:00:00', '2027-01-08T11:00:00', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' ),
			),
			22
		);
		$repository = new OccurrenceReadRepository( $this->builder(), $gateway );

		$page = $repository->query(
			new EventQueryCriteria( EventPeriod::UPCOMING, 10, 1, array(), array(), 1_700_000_000 )
		);

		self::assertCount( 2, $page->occurrences );
		self::assertSame( 42, $page->occurrences[0]->event_id );
		self::assertSame( 42, $page->occurrences[1]->event_id );
		self::assertNotSame( $page->occurrences[0]->public_key, $page->occurrences[1]->public_key );
		self::assertSame( 22, $page->total );
		self::assertSame( 3, $page->total_pages );
		self::assertNotNull( $gateway->rows_query );
		self::assertNotNull( $gateway->count_query );
	}

	/**
	 * UTC values that no longer match canonical local data disable the read path.
	 */
	public function test_repository_rejects_corrupt_projection_timestamps(): void {
		$row              = $this->row( '2027-01-01T10:00:00', '2027-01-01T11:00:00', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' );
		$row['start_utc'] = (int) $row['start_utc'] + 60;
		$repository       = new OccurrenceReadRepository(
			$this->builder(),
			new FakeOccurrenceReadGateway( array( $row ), 1 )
		);

		$this->expectException( OccurrenceReadException::class );

		$repository->query(
			new EventQueryCriteria( EventPeriod::ALL, 10, 1, array(), array(), 1_700_000_000 )
		);
	}

	/**
	 * A gateway cannot return more rows than requested or counted.
	 */
	public function test_repository_rejects_inconsistent_page_bounds(): void {
		$repository = new OccurrenceReadRepository(
			$this->builder(),
			new FakeOccurrenceReadGateway(
				array(
					$this->row( '2027-01-01T10:00:00', '2027-01-01T11:00:00', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
					$this->row( '2027-01-08T10:00:00', '2027-01-08T11:00:00', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' ),
				),
				1
			)
		);

		$this->expectException( OccurrenceReadException::class );

		$repository->query(
			new EventQueryCriteria( EventPeriod::ALL, 10, 1, array(), array(), 1_700_000_000 )
		);
	}

	/** One exact public key returns one validated active occurrence. */
	public function test_repository_finds_one_exact_public_occurrence(): void {
		$key        = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$gateway    = new FakeOccurrenceReadGateway(
			array( $this->row( '2027-01-01T10:00:00', '2027-01-01T11:00:00', $key ) ),
			1
		);
		$repository = new OccurrenceReadRepository( $this->builder(), $gateway );

		$occurrence = $repository->find_public( 42, $key );

		self::assertNotNull( $occurrence );
		self::assertSame( 42, $occurrence->event_id );
		self::assertSame( $key, $occurrence->public_key );
		self::assertNotNull( $gateway->rows_query );
		self::assertNull( $gateway->count_query );
	}

	/** Duplicate rows for one public identity fail closed. */
	public function test_repository_rejects_ambiguous_public_occurrence(): void {
		$key = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$row = $this->row( '2027-01-01T10:00:00', '2027-01-01T11:00:00', $key );

		$this->expectException( OccurrenceReadException::class );

		( new OccurrenceReadRepository(
			$this->builder(),
			new FakeOccurrenceReadGateway( array( $row, $row ), 2 )
		) )->find_public( 42, $key );
	}

	/** Storage results may not substitute another requested event or key. */
	public function test_repository_rejects_mismatched_public_occurrence(): void {
		$this->expectException( OccurrenceReadException::class );

		( new OccurrenceReadRepository(
			$this->builder(),
			new FakeOccurrenceReadGateway(
				array( $this->row( '2027-01-01T10:00:00', '2027-01-01T11:00:00', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' ) ),
				1
			)
		) )->find_public( 42, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' );
	}

	/** Sitemap pages preserve occurrence units and use their dedicated plan. */
	public function test_repository_returns_a_bounded_sitemap_page(): void {
		$gateway    = new FakeOccurrenceReadGateway(
			array( $this->row( '2027-01-01T10:00:00', '2027-01-01T11:00:00', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ) ),
			201
		);
		$repository = new OccurrenceReadRepository( $this->builder(), $gateway );
		$page       = $repository->query_sitemap( 100, 2 );

		self::assertCount( 1, $page->occurrences );
		self::assertSame( 201, $page->total );
		self::assertSame( 3, $page->total_pages );
		self::assertNotNull( $gateway->rows_query );
		self::assertStringContainsString( 'o.source <> %s', $gateway->rows_query->sql );
	}

	/** Sitemap totals do not load a candidate page. */
	public function test_repository_counts_sitemap_candidates_without_loading_rows(): void {
		$gateway    = new FakeOccurrenceReadGateway( array(), 201 );
		$repository = new OccurrenceReadRepository( $this->builder(), $gateway );

		self::assertSame( 201, $repository->count_sitemap( 100 ) );
		self::assertNull( $gateway->rows_query );
		self::assertNotNull( $gateway->count_query );
		self::assertStringContainsString( 'o.source <> %s', $gateway->count_query->sql );
	}

	/**
	 * Build one valid raw row from the same canonical date primitive as production.
	 *
	 * @param string $start_local Timed local start.
	 * @param string $end_local   Timed local end.
	 * @param string $public_key  Stable occurrence key.
	 * @return array<string, int|string>
	 */
	private function row( string $start_local, string $end_local, string $public_key ): array {
		$range = EventDateRange::from_local( $start_local, $end_local, false, 'Europe/Brussels' );

		return array(
			'event_id'      => 42,
			'public_key'    => $public_key,
			'recurrence_id' => $start_local,
			'generation'    => 123456,
			'segment_id'    => 1,
			'source'        => 'rule',
			'start_local'   => $range->start_local(),
			'end_local'     => $range->end_local(),
			'start_utc'     => $range->start_utc(),
			'end_utc'       => $range->end_utc(),
			'timezone'      => $range->timezone(),
			'all_day'       => 0,
			'event_status'  => 'scheduled',
		);
	}

	/**
	 * Return a deterministic occurrence query builder.
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
