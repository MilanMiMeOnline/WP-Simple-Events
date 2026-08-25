<?php
/**
 * Already-read occurrence presentation boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;

/**
 * Resolves an occurrence row that already passed the public projection query.
 */
interface ProjectedOccurrencePresentationProvider {
	/**
	 * Join one authorized recurring projection row to canonical inheritance.
	 *
	 * @param OccurrenceReadModel $occurrence Active public projection row.
	 */
	public function resolve_projected( OccurrenceReadModel $occurrence ): ?OccurrencePresentationContext;
}
