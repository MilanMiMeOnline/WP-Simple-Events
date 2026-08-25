<?php
/**
 * Presentation-ready occurrence result page.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use InvalidArgumentException;

/**
 * Preserves exact occurrence totals and repeated parent event IDs.
 */
final readonly class OccurrenceCollectionPage {
	/**
	 * Store one complete presentation page.
	 *
	 * @param array $items       Presentation-ready occurrence items.
	 * @param int   $total       Exact matching occurrence total.
	 * @param int   $total_pages Exact occurrence page count.
	 * @phpstan-param list<OccurrenceCollectionItem> $items
	 * @throws InvalidArgumentException When totals are inconsistent.
	 */
	public function __construct(
		public array $items,
		public int $total,
		public int $total_pages
	) {
		if ( $this->total < 0 || $this->total_pages < 0 || count( $this->items ) > $this->total ) {
			throw new InvalidArgumentException( 'The occurrence presentation page totals are invalid.' );
		}
	}
}
