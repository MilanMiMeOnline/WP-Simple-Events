<?php
/**
 * In-memory occurrence read gateway.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadGateway;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceSqlQuery;

/**
 * Supplies fixed raw rows and records both generated SQL statements.
 */
final class FakeOccurrenceReadGateway implements OccurrenceReadGateway {
	/**
	 * Last row query submitted by the repository.
	 *
	 * @var OccurrenceSqlQuery|null
	 */
	public ?OccurrenceSqlQuery $rows_query = null;

	/**
	 * Last count query submitted by the repository.
	 *
	 * @var OccurrenceSqlQuery|null
	 */
	public ?OccurrenceSqlQuery $count_query = null;

	/**
	 * Configure deterministic read results.
	 *
	 * @param list<array<string, mixed>> $rows Raw occurrence rows.
	 * @param int                        $total Exact matching total.
	 */
	public function __construct(
		private readonly array $rows,
		private readonly int $total
	) {}

	/**
	 * Return the configured row page.
	 *
	 * @param OccurrenceSqlQuery $query Generated row query.
	 * @return list<array<string, mixed>>
	 */
	public function rows( OccurrenceSqlQuery $query ): array {
		$this->rows_query = $query;

		return $this->rows;
	}

	/**
	 * Return the configured exact total.
	 *
	 * @param OccurrenceSqlQuery $query Generated count query.
	 */
	public function count( OccurrenceSqlQuery $query ): int {
		$this->count_query = $query;

		return $this->total;
	}
}
