<?php
/**
 * WordPress inactive occurrence-generation cleanup.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;

/**
 * Executes one guarded, bounded maintenance batch against the disposable table.
 */
final class WordPressOccurrenceGenerationCleaner implements OccurrenceGenerationCleaner {
	/**
	 * Create the WordPress cleanup adapter.
	 *
	 * @param OccurrenceTable $table Projection table lifecycle and name.
	 */
	public function __construct(
		private readonly OccurrenceTable $table = new OccurrenceTable()
	) {}

	/**
	 * Remove only selected rows that remain inactive, old and clean at deletion.
	 *
	 * @param int $cutoff_utc Oldest permitted creation timestamp, inclusive.
	 * @param int $limit      Maximum rows to remove.
	 * @return int|null Removed row count, or null on a database/contract failure.
	 */
	public function clean_before( int $cutoff_utc, int $limit ): ?int {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return null;
		}

		try {
			$builder = new OccurrenceGenerationCleanupQueryBuilder(
				$this->table->table_name(),
				$wpdb->postmeta
			);
			$select  = $this->prepare( $wpdb, $builder->candidates( $cutoff_utc, $limit ) );
		} catch ( InvalidArgumentException | OccurrenceReadException ) {
			return null;
		}

		$raw_ids = $wpdb->get_col( $select ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Dedicated bounded maintenance adapter; the strictly typed internal query contract was validated and prepared immediately above.

		if ( $this->has_database_error( $wpdb ) ) {
			return null;
		}

		$ids = array();

		foreach ( $raw_ids as $raw_id ) {
			$id = filter_var( $raw_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );

			if ( false === $id ) {
				return null;
			}

			$ids[] = $id;
		}

		if ( array() === $ids ) {
			return 0;
		}

		try {
			$delete = $this->prepare( $wpdb, $builder->delete( $ids, $cutoff_utc ) );
		} catch ( InvalidArgumentException | OccurrenceReadException ) {
			return null;
		}

		$deleted = $wpdb->query( $delete ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Guarded uncached deletion uses a strictly typed prepared contract and repeats active-generation, dirty-marker, age and bounded-ID predicates.

		return is_int( $deleted ) && ! $this->has_database_error( $wpdb ) ? $deleted : null;
	}

	/**
	 * Compile a strictly typed internal SQL contract through WordPress escaping.
	 *
	 * @param \wpdb              $wpdb WordPress database adapter.
	 * @param OccurrenceSqlQuery $query Validated internal query contract.
	 * @throws OccurrenceReadException When preparation fails.
	 */
	private function prepare( \wpdb $wpdb, OccurrenceSqlQuery $query ): string {
		$parameter_index = 0;
		$prepared        = preg_replace_callback(
			'/%[dfs]/D',
			static function ( array $matches ) use ( $wpdb, $query, &$parameter_index ): string {
				$placeholder = $matches[0];
				$parameter   = $query->parameters[ $parameter_index ];
				++$parameter_index;

				if ( '%d' === $placeholder && is_int( $parameter ) ) {
					$value = $wpdb->prepare( '%d', $parameter );
				} elseif ( '%f' === $placeholder && ( is_int( $parameter ) || is_float( $parameter ) ) ) {
					$value = $wpdb->prepare( '%f', $parameter );
				} elseif ( '%s' === $placeholder && is_string( $parameter ) ) {
					$value = $wpdb->prepare( '%s', $parameter );
				} else {
					throw new OccurrenceReadException( 'The occurrence cleanup query contains an invalid placeholder value.' );
				}

				if ( ! is_string( $value ) ) {
					throw new OccurrenceReadException( 'The occurrence cleanup query value could not be prepared.' );
				}

				return $value;
			},
			$query->sql
		);

		if ( ! is_string( $prepared ) || count( $query->parameters ) !== $parameter_index ) {
			throw new OccurrenceReadException( 'The occurrence cleanup query could not be prepared.' );
		}

		return $prepared;
	}

	/**
	 * Read wpdb's mutable public error state without assuming query purity.
	 *
	 * @param \wpdb $wpdb WordPress database adapter.
	 * @phpstan-impure Database state may change after every wpdb operation.
	 */
	private function has_database_error( \wpdb $wpdb ): bool {
		$state = get_object_vars( $wpdb );
		$error = $state['last_error'] ?? null;

		return is_string( $error ) && '' !== $error;
	}
}
