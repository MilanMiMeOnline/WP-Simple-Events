<?php
/**
 * Occurrence read query plan.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Couples a bounded result query to the exact matching count query.
 */
final readonly class OccurrenceReadQueryPlan {
	/**
	 * Create one read plan.
	 *
	 * @param OccurrenceSqlQuery $rows  Bounded row query.
	 * @param OccurrenceSqlQuery $count Matching total-count query.
	 * @param int                $limit Requested page size.
	 */
	public function __construct(
		public OccurrenceSqlQuery $rows,
		public OccurrenceSqlQuery $count,
		public int $limit
	) {}
}
