<?php
/**
 * Bounded inactive occurrence-generation cleanup SQL.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;

/**
 * Builds selection and guarded deletion queries for disposable stale rows.
 */
final readonly class OccurrenceGenerationCleanupQueryBuilder {
	/**
	 * Create a builder from trusted WordPress table identifiers.
	 *
	 * @param string $occurrences_table Plugin occurrence table.
	 * @param string $posts_table       WordPress posts table.
	 * @param string $postmeta_table    WordPress postmeta table.
	 * @throws InvalidArgumentException When a table identifier is unsafe.
	 */
	public function __construct(
		private string $occurrences_table,
		private string $posts_table,
		private string $postmeta_table
	) {
		foreach ( array( $this->occurrences_table, $this->posts_table, $this->postmeta_table ) as $table_name ) {
			if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $table_name ) ) {
				throw new InvalidArgumentException( 'An occurrence cleanup table name is invalid.' );
			}
		}
	}

	/**
	 * Select one deterministic batch of inactive, old and clean row IDs.
	 *
	 * @param int $cutoff_utc Oldest permitted creation timestamp, inclusive.
	 * @param int $limit      Maximum candidate rows.
	 * @throws InvalidArgumentException When cleanup bounds are invalid.
	 */
	public function candidates( int $cutoff_utc, int $limit ): OccurrenceSqlQuery {
		$this->validate_bounds( $cutoff_utc, $limit );

		return new OccurrenceSqlQuery(
			'SELECT DISTINCT o.id' . $this->joins()
			. ' WHERE o.created_utc <= %d'
			. ' AND (p.ID IS NULL OR p.post_type <> %s'
			. ' OR (ag.post_id IS NOT NULL AND dg.post_id IS NULL'
			. ' AND o.generation <> CAST(ag.meta_value AS UNSIGNED)))'
			. ' ORDER BY o.id ASC LIMIT %d',
			array(
				EventMeta::ACTIVE_GENERATION,
				EventMeta::INDEX_DIRTY,
				$cutoff_utc,
				EventPostType::POST_TYPE,
				$limit,
			)
		);
	}

	/**
	 * Delete selected IDs only after repeating all mutable eligibility checks.
	 *
	 * @param array $ids        Previously selected positive row IDs.
	 * @param int   $cutoff_utc Oldest permitted creation timestamp, inclusive.
	 * @phpstan-param list<int> $ids
	 * @throws InvalidArgumentException When IDs or cleanup bounds are invalid.
	 */
	public function delete( array $ids, int $cutoff_utc ): OccurrenceSqlQuery {
		$this->validate_bounds( $cutoff_utc, count( $ids ) );

		$validated_ids = array_values( array_unique( $ids ) );

		if ( count( $validated_ids ) !== count( $ids ) ) {
			throw new InvalidArgumentException( 'Occurrence cleanup row IDs must be unique.' );
		}

		foreach ( $validated_ids as $id ) {
			if ( $id <= 0 ) {
				throw new InvalidArgumentException( 'Occurrence cleanup row IDs must be positive.' );
			}
		}

		$placeholders = implode( ', ', array_fill( 0, count( $validated_ids ), '%d' ) );

		return new OccurrenceSqlQuery(
			'DELETE o' . $this->joins()
			. " WHERE o.id IN ({$placeholders})"
			. ' AND o.created_utc <= %d'
			. ' AND (p.ID IS NULL OR p.post_type <> %s'
			. ' OR (ag.post_id IS NOT NULL AND dg.post_id IS NULL'
			. ' AND o.generation <> CAST(ag.meta_value AS UNSIGNED)))',
			array_merge(
				array( EventMeta::ACTIVE_GENERATION, EventMeta::INDEX_DIRTY ),
				$validated_ids,
				array( $cutoff_utc, EventPostType::POST_TYPE )
			)
		);
	}

	/** Return joins that distinguish missing parents from healthy active generations. */
	private function joins(): string {
		return " FROM {$this->occurrences_table} o"
			. " LEFT JOIN {$this->posts_table} p ON p.ID = o.event_id"
			. " LEFT JOIN {$this->postmeta_table} ag ON ag.post_id = o.event_id AND ag.meta_key = %s"
			. " LEFT JOIN {$this->postmeta_table} dg ON dg.post_id = o.event_id AND dg.meta_key = %s";
	}

	/**
	 * Enforce the maintenance query's fixed safety bounds.
	 *
	 * @param int $cutoff_utc Positive UTC timestamp.
	 * @param int $limit      Batch size from 1 through 100.
	 * @throws InvalidArgumentException When cleanup bounds are invalid.
	 */
	private function validate_bounds( int $cutoff_utc, int $limit ): void {
		if ( $cutoff_utc <= 0 || $limit < 1 || $limit > 100 ) {
			throw new InvalidArgumentException( 'Occurrence cleanup bounds are invalid.' );
		}
	}
}
