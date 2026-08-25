<?php
/**
 * Validated occurrence read repository.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Query\EventWindowCriteria;

/**
 * Maps occurrence-level database results without deduplicating their series IDs.
 */
final readonly class OccurrenceReadRepository {
	/**
	 * Create the repository with replaceable query and storage boundaries.
	 *
	 * @param OccurrenceReadQueryBuilder|null $queries Prepared-query planner.
	 * @param OccurrenceReadGateway           $gateway Projection storage gateway.
	 */
	public function __construct(
		private ?OccurrenceReadQueryBuilder $queries = null,
		private OccurrenceReadGateway $gateway = new WordPressOccurrenceReadGateway()
	) {}

	/**
	 * Return one chronological occurrence page.
	 *
	 * @param EventQueryCriteria $criteria Validated chronological criteria.
	 */
	public function query( EventQueryCriteria $criteria ): OccurrencePage {
		return $this->execute( $this->query_builder()->build( $criteria ) );
	}

	/**
	 * Return one calendar-overlap occurrence page.
	 *
	 * @param EventWindowCriteria $criteria Validated calendar window.
	 */
	public function query_window( EventWindowCriteria $criteria ): OccurrencePage {
		return $this->execute( $this->query_builder()->build_window( $criteria ) );
	}

	/**
	 * Resolve one exact active public occurrence without accepting ambiguity.
	 *
	 * @param int    $event_id   Canonical event post ID.
	 * @param string $public_key Stable occurrence public key.
	 * @throws InvalidArgumentException When the requested identity is malformed.
	 * @throws OccurrenceReadException When storage returns corrupt or duplicate state.
	 */
	public function find_public( int $event_id, string $public_key ): ?OccurrenceReadModel {
		if ( $event_id <= 0 || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $public_key ) ) {
			throw new InvalidArgumentException( 'A public occurrence lookup requires canonical identities.' );
		}

		$rows = $this->gateway->rows(
			$this->query_builder()->build_public_identity( $event_id, $public_key )
		);

		if ( array() === $rows ) {
			return null;
		}

		if ( 1 !== count( $rows ) ) {
			throw new OccurrenceReadException( 'The public occurrence identity is ambiguous.' );
		}

		try {
			$occurrence = OccurrenceReadModel::from_row( $rows[0] );
		} catch ( InvalidArgumentException ) {
			throw new OccurrenceReadException( 'The occurrence projection contains invalid data.' );
		}

		if ( $occurrence->event_id !== $event_id || ! hash_equals( $occurrence->public_key, $public_key ) ) {
			throw new OccurrenceReadException( 'The occurrence result does not match its requested identity.' );
		}

		return $occurrence;
	}

	/**
	 * Return one bounded page of recurring candidates for public sitemap validation.
	 *
	 * @param int $limit Maximum rows in the page.
	 * @param int $page  One-based page number.
	 */
	public function query_sitemap( int $limit, int $page ): OccurrencePage {
		return $this->execute( $this->query_builder()->build_sitemap( $limit, $page ) );
	}

	/**
	 * Count active recurring sitemap candidates without loading their rows.
	 *
	 * @param int $limit Page size used by the bounded sitemap provider.
	 */
	public function count_sitemap( int $limit ): int {
		return $this->gateway->count( $this->query_builder()->build_sitemap_count( $limit ) );
	}

	/**
	 * Execute and validate one complete page plan.
	 *
	 * @param OccurrenceReadQueryPlan $plan Prepared rows-and-count plan.
	 * @throws OccurrenceReadException When storage or projection values are inconsistent.
	 */
	private function execute( OccurrenceReadQueryPlan $plan ): OccurrencePage {
		$rows  = $this->gateway->rows( $plan->rows );
		$total = $this->gateway->count( $plan->count );

		if ( count( $rows ) > $plan->limit || count( $rows ) > $total ) {
			throw new OccurrenceReadException( 'The occurrence result page exceeds its reported bounds.' );
		}

		try {
			$occurrences = array_map( OccurrenceReadModel::from_row( ... ), $rows );
		} catch ( InvalidArgumentException ) {
			throw new OccurrenceReadException( 'The occurrence projection contains invalid data.' );
		}

		$total_pages = 0 === $total ? 0 : (int) ceil( $total / $plan->limit );

		return new OccurrencePage( $occurrences, $total, $total_pages );
	}

	/**
	 * Resolve WordPress table names only when a read is explicitly requested.
	 *
	 * @throws OccurrenceReadException When the WordPress database is unavailable.
	 */
	private function query_builder(): OccurrenceReadQueryBuilder {
		if ( null !== $this->queries ) {
			return $this->queries;
		}

		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			throw new OccurrenceReadException( 'The WordPress database is unavailable.' );
		}

		return new OccurrenceReadQueryBuilder(
			( new OccurrenceTable() )->table_name(),
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->term_relationships,
			$wpdb->term_taxonomy,
			$wpdb->terms
		);
	}
}
