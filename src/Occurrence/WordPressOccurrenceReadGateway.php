<?php
/**
 * WordPress occurrence read gateway.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Executes only internally generated, prepared reads against the derived table.
 */
final class WordPressOccurrenceReadGateway implements OccurrenceReadGateway {
	/**
	 * Fetch raw occurrence rows.
	 *
	 * @param OccurrenceSqlQuery $query Internally generated prepared-query contract.
	 * @return list<array<string, mixed>>
	 * @throws OccurrenceReadException When WordPress cannot prepare or execute the read.
	 */
	public function rows( OccurrenceSqlQuery $query ): array {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			throw new OccurrenceReadException( 'The WordPress database is unavailable.' );
		}

		$sql = $this->compile( $wpdb, $query );

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Dedicated adapter for the disposable projection; the strictly typed internal query contract was prepared immediately above and parent visibility is rechecked by the query.

		if ( '' !== $wpdb->last_error || null === $rows ) {
			throw new OccurrenceReadException( 'The occurrence row query failed.' );
		}

		$normalized_rows = array();

		foreach ( $rows as $row ) {
			$normalized_row = array();

			foreach ( $row as $column => $value ) {
				if ( ! is_string( $column ) ) {
					throw new OccurrenceReadException( 'The occurrence row query returned an invalid column.' );
				}

				$normalized_row[ $column ] = $value;
			}

			$normalized_rows[] = $normalized_row;
		}

		return $normalized_rows;
	}

	/**
	 * Count matching occurrence rows.
	 *
	 * @param OccurrenceSqlQuery $query Internally generated prepared-query contract.
	 * @throws OccurrenceReadException When WordPress cannot prepare or execute the count.
	 */
	public function count( OccurrenceSqlQuery $query ): int {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			throw new OccurrenceReadException( 'The WordPress database is unavailable.' );
		}

		$sql = $this->compile( $wpdb, $query );

		$count = $wpdb->get_var( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Dedicated adapter for the disposable projection; the strictly typed internal query contract was prepared immediately above and exact totals cannot be served from object cache.

		if ( '' !== $wpdb->last_error || ! is_string( $count ) ) {
			throw new OccurrenceReadException( 'The occurrence count query returned an invalid total.' );
		}

		$validated_count = filter_var( $count, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );

		if ( false === $validated_count ) {
			throw new OccurrenceReadException( 'The occurrence count query returned an invalid total.' );
		}

		return $validated_count;
	}

	/**
	 * Compile one validated internal template using WordPress' escaping per value.
	 *
	 * OccurrenceSqlQuery has already proved that only %d, %f and %s occur and that
	 * every placeholder has one value of the corresponding type. Keeping each
	 * prepare format literal preserves wpdb's security contract without treating
	 * a dynamically assembled SQL structure as a literal string.
	 *
	 * @param \wpdb              $wpdb WordPress database adapter.
	 * @param OccurrenceSqlQuery $query Validated internal query contract.
	 * @throws OccurrenceReadException When WordPress cannot escape a value.
	 */
	private function compile( \wpdb $wpdb, OccurrenceSqlQuery $query ): string {
		$parameter_index = 0;
		$sql             = preg_replace_callback(
			'/%[dfs]/D',
			static function ( array $matches ) use ( $wpdb, $query, &$parameter_index ): string {
				$placeholder = $matches[0];
				$parameter   = $query->parameters[ $parameter_index ];
				++$parameter_index;

				if ( '%d' === $placeholder && is_int( $parameter ) ) {
					$prepared = $wpdb->prepare( '%d', $parameter );
				} elseif ( '%f' === $placeholder && ( is_int( $parameter ) || is_float( $parameter ) ) ) {
					$prepared = $wpdb->prepare( '%f', $parameter );
				} elseif ( '%s' === $placeholder && is_string( $parameter ) ) {
					$prepared = $wpdb->prepare( '%s', $parameter );
				} else {
					throw new OccurrenceReadException( 'The occurrence query contains an invalid placeholder value.' );
				}

				if ( ! is_string( $prepared ) ) {
					throw new OccurrenceReadException( 'The occurrence query value could not be prepared.' );
				}

				return $prepared;
			},
			$query->sql
		);

		if ( ! is_string( $sql ) || count( $query->parameters ) !== $parameter_index ) {
			throw new OccurrenceReadException( 'The occurrence query could not be compiled.' );
		}

		return $sql;
	}
}
