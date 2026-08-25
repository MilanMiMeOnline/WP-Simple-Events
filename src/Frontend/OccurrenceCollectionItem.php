<?php
/**
 * One occurrence collection presentation item.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;

/**
 * Keeps the occurrence identity next to its shared event presentation.
 */
final readonly class OccurrenceCollectionItem {
	/**
	 * Store one exact occurrence and its effective public fields.
	 *
	 * @param OccurrenceReadModel $occurrence   Active public projection row.
	 * @param EventPresentation   $presentation Effective public presentation.
	 */
	public function __construct(
		public OccurrenceReadModel $occurrence,
		public EventPresentation $presentation
	) {}
}
