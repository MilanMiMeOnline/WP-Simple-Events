<?php
/**
 * Public occurrence SQL query construction.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;

/**
 * Produces bounded, permission-aware plans for the disposable projection table.
 */
final readonly class OccurrenceReadQueryBuilder {
	/**
	 * Create a builder from trusted WordPress table identifiers.
	 *
	 * @param string $occurrences_table       Plugin occurrence table.
	 * @param string $posts_table             WordPress posts table.
	 * @param string $postmeta_table          WordPress postmeta table.
	 * @param string $term_relationships_table WordPress term relationships table.
	 * @param string $term_taxonomy_table     WordPress term taxonomy table.
	 * @param string $terms_table             WordPress terms table.
	 * @throws InvalidArgumentException When a table identifier is unsafe.
	 */
	public function __construct(
		private string $occurrences_table,
		private string $posts_table,
		private string $postmeta_table,
		private string $term_relationships_table,
		private string $term_taxonomy_table,
		private string $terms_table
	) {
		foreach ( get_object_vars( $this ) as $table_name ) {
			if ( ! is_string( $table_name ) || 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $table_name ) ) {
				throw new InvalidArgumentException( 'An occurrence query table name is invalid.' );
			}
		}
	}

	/**
	 * Build a chronological period query with exact occurrence pagination.
	 *
	 * @param EventQueryCriteria $criteria Validated chronological criteria.
	 */
	public function build( EventQueryCriteria $criteria ): OccurrenceReadQueryPlan {
		$conditions = $this->base_conditions();
		$parameters = $this->base_parameters();

		if ( EventPeriod::UPCOMING === $criteria->period ) {
			$conditions[] = 'o.end_utc >= %d';
			$parameters[] = $criteria->now_utc;
			$conditions[] = 'o.event_status <> %s';
			$parameters[] = EventStatus::CANCELLED->value;
		} elseif ( EventPeriod::PAST === $criteria->period ) {
			$conditions[] = 'o.end_utc < %d';
			$parameters[] = $criteria->now_utc;
		}

		$this->add_taxonomy_filter( $conditions, $parameters, EventTaxonomies::CATEGORY, array_values( $criteria->category_slugs ) );
		$this->add_taxonomy_filter( $conditions, $parameters, EventTaxonomies::TAG, array_values( $criteria->tag_slugs ) );

		$direction = EventPeriod::PAST === $criteria->period ? 'DESC' : 'ASC';

		return $this->plan( $conditions, $parameters, $criteria->limit, $criteria->page, $direction, 'o.start_utc' );
	}

	/**
	 * Build an inclusive-end/exclusive-request-end calendar overlap query.
	 *
	 * @param EventWindowCriteria $criteria Validated local calendar window.
	 */
	public function build_window( EventWindowCriteria $criteria ): OccurrenceReadQueryPlan {
		$conditions   = $this->base_conditions();
		$conditions[] = 'o.end_local >= %s';
		$conditions[] = 'o.start_local < %s';
		$parameters   = array_merge(
			$this->base_parameters(),
			array( $criteria->window->start_local, $criteria->window->end_exclusive_local )
		);

		$this->add_taxonomy_filter( $conditions, $parameters, EventTaxonomies::CATEGORY, array_values( $criteria->category_slugs ) );
		$this->add_taxonomy_filter( $conditions, $parameters, EventTaxonomies::TAG, array_values( $criteria->tag_slugs ) );

		return $this->plan( $conditions, $parameters, $criteria->limit, $criteria->page, 'ASC', 'o.start_local' );
	}

	/**
	 * Build one exact public occurrence identity lookup.
	 *
	 * Two rows are requested deliberately so corrupt duplicate active identities
	 * can be detected by the repository instead of being hidden by LIMIT 1.
	 *
	 * @param int    $event_id   Canonical event post ID.
	 * @param string $public_key Stable lowercase occurrence key.
	 * @throws InvalidArgumentException When either identity is invalid.
	 */
	public function build_public_identity( int $event_id, string $public_key ): OccurrenceSqlQuery {
		if ( $event_id <= 0 || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $public_key ) ) {
			throw new InvalidArgumentException( 'A public occurrence lookup requires canonical identities.' );
		}

		$conditions   = $this->base_conditions();
		$conditions[] = 'o.event_id = %d';
		$conditions[] = 'o.public_key = %s';
		$parameters   = array_merge( $this->base_parameters(), array( $event_id, $public_key ) );

		return new OccurrenceSqlQuery(
			$this->select_clause()
			. " FROM {$this->occurrences_table} o INNER JOIN {$this->posts_table} p ON p.ID = o.event_id"
			. ' WHERE ' . implode( ' AND ', $conditions )
			. ' ORDER BY o.event_id ASC, o.public_key ASC LIMIT 2',
			$parameters
		);
	}

	/**
	 * Build one bounded sitemap page from recurring projection rows only.
	 *
	 * @param int $limit Maximum rows in the page.
	 * @param int $page  One-based page number.
	 * @throws InvalidArgumentException When pagination is outside the sitemap contract.
	 */
	public function build_sitemap( int $limit, int $page ): OccurrenceReadQueryPlan {
		$this->validate_sitemap_pagination( $limit, $page );

		$conditions   = $this->base_conditions();
		$conditions[] = 'o.source <> %s';
		$parameters   = array_merge( $this->base_parameters(), array( OccurrenceSource::ONE_OFF->value ) );

		return $this->plan( $conditions, $parameters, $limit, $page, 'ASC', 'o.start_utc' );
	}

	/**
	 * Build the exact count query used by the recurring occurrence sitemap index.
	 *
	 * @param int $limit Page size used to validate the shared bounded contract.
	 * @throws InvalidArgumentException When the limit is outside the sitemap contract.
	 */
	public function build_sitemap_count( int $limit ): OccurrenceSqlQuery {
		$this->validate_sitemap_pagination( $limit, 1 );

		$conditions   = $this->base_conditions();
		$conditions[] = 'o.source <> %s';
		$parameters   = array_merge( $this->base_parameters(), array( OccurrenceSource::ONE_OFF->value ) );
		$from         = " FROM {$this->occurrences_table} o INNER JOIN {$this->posts_table} p ON p.ID = o.event_id";

		return new OccurrenceSqlQuery(
			'SELECT COUNT(*)' . $from . ' WHERE ' . implode( ' AND ', $conditions ),
			$parameters
		);
	}

	/**
	 * Return visibility and active-generation predicates shared by every public read.
	 *
	 * @return list<string>
	 */
	private function base_conditions(): array {
		return array(
			'p.post_type = %s',
			'p.post_status = %s',
			"p.post_password = ''",
			"EXISTS (SELECT 1 FROM {$this->postmeta_table} ag WHERE ag.post_id = o.event_id AND ag.meta_key = %s AND CAST(ag.meta_value AS UNSIGNED) = o.generation)",
			"(o.source = %s OR EXISTS (SELECT 1 FROM {$this->postmeta_table} cg WHERE cg.post_id = o.event_id AND cg.meta_key = %s AND CAST(cg.meta_value AS UNSIGNED) = o.generation))",
			"NOT EXISTS (SELECT 1 FROM {$this->postmeta_table} dg WHERE dg.post_id = o.event_id AND dg.meta_key = %s)",
		);
	}

	/**
	 * Return placeholder values for the shared visibility predicates.
	 *
	 * @return list<int|float|string>
	 */
	private function base_parameters(): array {
		return array(
			EventPostType::POST_TYPE,
			'publish',
			EventMeta::ACTIVE_GENERATION,
			OccurrenceSource::ONE_OFF->value,
			EventMeta::COVERAGE_GENERATION,
			EventMeta::INDEX_DIRTY,
		);
	}

	/**
	 * Enforce the fixed sitemap query ceiling independently from WordPress filters.
	 *
	 * @param int $limit Maximum rows in one page.
	 * @param int $page  One-based page number.
	 * @throws InvalidArgumentException When pagination is unsafe.
	 */
	private function validate_sitemap_pagination( int $limit, int $page ): void {
		if ( $limit < 1 || $limit > 100 || $page < 1 ) {
			throw new InvalidArgumentException( 'Occurrence sitemap pagination is outside its bounded contract.' );
		}
	}

	/**
	 * Add one canonical taxonomy EXISTS predicate with OR-within-taxonomy semantics.
	 *
	 * @param array  $conditions SQL predicates.
	 * @param array  $parameters Placeholder values.
	 * @param string $taxonomy   Canonical taxonomy name.
	 * @param array  $slugs      Normalized term slugs.
	 * @phpstan-param list<string> $conditions
	 * @phpstan-param list<int|float|string> $parameters
	 * @phpstan-param list<string> $slugs
	 */
	private function add_taxonomy_filter( array &$conditions, array &$parameters, string $taxonomy, array $slugs ): void {
		if ( array() === $slugs ) {
			return;
		}

		$slug_placeholders = implode( ', ', array_fill( 0, count( $slugs ), '%s' ) );
		$conditions[]      = "EXISTS (SELECT 1 FROM {$this->term_relationships_table} tr INNER JOIN {$this->term_taxonomy_table} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$this->terms_table} t ON t.term_id = tt.term_id WHERE tr.object_id = o.event_id AND tt.taxonomy = %s AND t.slug IN ({$slug_placeholders}))";
		$parameters[]      = $taxonomy;

		foreach ( $slugs as $slug ) {
			$parameters[] = $slug;
		}
	}

	/**
	 * Finalize matching data and count statements.
	 *
	 * @param array  $conditions    Shared WHERE predicates.
	 * @param array  $parameters    Shared placeholder values.
	 * @param int    $limit         Page size.
	 * @param int    $page          One-based page.
	 * @param string $direction     Validated internal sort direction.
	 * @param string $primary_order Validated internal primary sort expression.
	 * @phpstan-param list<string> $conditions
	 * @phpstan-param list<int|float|string> $parameters
	 */
	private function plan(
		array $conditions,
		array $parameters,
		int $limit,
		int $page,
		string $direction,
		string $primary_order
	): OccurrenceReadQueryPlan {
		$from   = " FROM {$this->occurrences_table} o INNER JOIN {$this->posts_table} p ON p.ID = o.event_id";
		$where  = ' WHERE ' . implode( ' AND ', $conditions );
		$select = $this->select_clause();
		$order  = " ORDER BY {$primary_order} {$direction}, o.event_id ASC, o.public_key ASC";
		$offset = ( $page - 1 ) * $limit;

		return new OccurrenceReadQueryPlan(
			new OccurrenceSqlQuery(
				$select . $from . $where . $order . ' LIMIT %d OFFSET %d',
				array_merge( $parameters, array( $limit, $offset ) )
			),
			new OccurrenceSqlQuery( 'SELECT COUNT(*)' . $from . $where, $parameters ),
			$limit
		);
	}

	/** Return the complete validated projection field selection. */
	private function select_clause(): string {
		return 'SELECT o.event_id, o.public_key, o.recurrence_id, o.generation, o.segment_id, o.source, o.start_local, o.end_local, o.start_utc, o.end_utc, o.timezone, o.all_day, o.event_status';
	}
}
