<?php
/**
 * Event admin-list query arguments.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Content\EventMeta;

/**
 * Builds allowlisted query fragments for the Events list table.
 */
final class AdminEventListQuery {
	/**
	 * Build query fragments for one requested admin view and sort.
	 *
	 * @param string $view       Requested event view.
	 * @param string $orderby    Requested sort key.
	 * @param string $order      Requested sort direction.
	 * @param int    $now_utc    Current UTC timestamp.
	 * @return array<string, mixed>
	 */
	public function arguments( string $view, string $orderby, string $order, int $now_utc ): array {
		$arguments = match ( $view ) {
			'upcoming' => $this->period_arguments( '>=', 'ASC', $now_utc ),
			'past' => $this->period_arguments( '<', 'DESC', $now_utc ),
			'cancelled', 'postponed' => array(
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The bounded Events admin list must filter its registered status metadata.
				'meta_query' => array(
					array(
						'key'     => EventMeta::STATUS,
						'value'   => $view,
						'compare' => '=',
					),
				),
			),
			default => array(),
		};

		$sort_meta_key = match ( $orderby ) {
			'wpse_start' => EventMeta::START_UTC,
			'wpse_end' => EventMeta::END_UTC,
			default => '',
		};

		if ( '' !== $sort_meta_key ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting the paginated Events admin list by its derived date metadata is intentional.
			$arguments['meta_key'] = $sort_meta_key;
			$arguments['orderby']  = 'meta_value_num';
			$arguments['order']    = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';
		}

		return $arguments;
	}

	/**
	 * Build an inclusive active or strict past query.
	 *
	 * @param string $comparison Numeric comparison operator.
	 * @param string $order      Default start order.
	 * @param int    $now_utc    Current UTC timestamp.
	 * @return array<string, mixed>
	 */
	private function period_arguments( string $comparison, string $order, int $now_utc ): array {
		return array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The paginated Events admin list is intentionally ordered by its indexed date metadata.
			'meta_key'   => EventMeta::START_UTC,
			'orderby'    => 'meta_value_num',
			'order'      => $order,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The Events admin view must select its requested date period from registered metadata.
			'meta_query' => array(
				array(
					'key'     => EventMeta::END_UTC,
					'value'   => $now_utc,
					'compare' => $comparison,
					'type'    => 'NUMERIC',
				),
			),
		);
	}
}
