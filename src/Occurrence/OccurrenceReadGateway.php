<?php
/**
 * Occurrence read storage boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Isolates direct projection-table reads from application mapping and validation.
 */
interface OccurrenceReadGateway {
	/**
	 * Fetch one bounded page of raw projection rows.
	 *
	 * @param OccurrenceSqlQuery $query Internally generated prepared-query contract.
	 * @return list<array<string, mixed>>
	 */
	public function rows( OccurrenceSqlQuery $query ): array;

	/**
	 * Count every row matching the page's visibility and filter predicates.
	 *
	 * @param OccurrenceSqlQuery $query Internally generated prepared-query contract.
	 */
	public function count( OccurrenceSqlQuery $query ): int;
}
