<?php
/**
 * Prepared occurrence SQL input.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;

/**
 * Keeps a generated SQL template coupled to its scalar placeholder values.
 */
final readonly class OccurrenceSqlQuery {
	/**
	 * Create one internal prepared-query contract.
	 *
	 * @param string                 $sql        SQL containing wpdb placeholders.
	 * @param list<int|float|string> $parameters Placeholder values in SQL order.
	 * @throws InvalidArgumentException When no SQL was supplied.
	 */
	public function __construct(
		public string $sql,
		public array $parameters
	) {
		if ( '' === trim( $this->sql ) ) {
			throw new InvalidArgumentException( 'An occurrence SQL query cannot be empty.' );
		}

		if ( 1 === preg_match( '/%(?![dfs])/D', $this->sql ) ) {
			throw new InvalidArgumentException( 'An occurrence SQL query contains an unsupported placeholder.' );
		}

		preg_match_all( '/%[dfs]/D', $this->sql, $placeholder_matches );
		$placeholders = $placeholder_matches[0];

		if ( count( $placeholders ) !== count( $this->parameters ) ) {
			throw new InvalidArgumentException( 'An occurrence SQL query has an invalid placeholder count.' );
		}

		foreach ( $placeholders as $index => $placeholder ) {
			$parameter = $this->parameters[ $index ];

			if ( '%d' === $placeholder && ! is_int( $parameter ) ) {
				throw new InvalidArgumentException( 'An occurrence integer placeholder requires an integer.' );
			}

			if ( '%f' === $placeholder && ! is_int( $parameter ) && ! is_float( $parameter ) ) {
				throw new InvalidArgumentException( 'An occurrence float placeholder requires a number.' );
			}

			if ( '%s' === $placeholder && ! is_string( $parameter ) ) {
				throw new InvalidArgumentException( 'An occurrence string placeholder requires a string.' );
			}
		}
	}
}
