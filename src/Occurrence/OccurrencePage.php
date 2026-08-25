<?php
/**
 * Paginated occurrence results.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;

/**
 * Reports occurrence-level totals without collapsing duplicate series IDs.
 */
final readonly class OccurrencePage {
	/**
	 * Create one result page.
	 *
	 * @param array $occurrences Validated occurrence rows.
	 * @param int   $total       Exact number of matching occurrences.
	 * @param int   $total_pages Exact number of result pages.
	 * @phpstan-param list<OccurrenceReadModel> $occurrences
	 * @throws InvalidArgumentException When pagination values are inconsistent.
	 */
	public function __construct(
		public array $occurrences,
		public int $total,
		public int $total_pages
	) {
		if ( $this->total < 0 || $this->total_pages < 0 || count( $this->occurrences ) > $this->total ) {
			throw new InvalidArgumentException( 'The occurrence page totals are invalid.' );
		}
	}
}
