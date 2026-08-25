<?php
/**
 * Shared normalized public occurrence presentation context.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;

/**
 * Carries one exact occurrence plus its normalized effective presentation fields.
 */
final readonly class OccurrencePresentationContext {
	/**
	 * Store one resolved occurrence presentation snapshot.
	 *
	 * @param EventPresentation   $series            Series-owned content and taxonomies.
	 * @param OccurrenceReadModel $occurrence        Exact active projection row.
	 * @param string              $title             Effective occurrence title.
	 * @param string              $note              Effective bounded occurrence note.
	 * @param int                 $featured_image_id Effective image ID, or zero.
	 * @param string              $venue             Effective venue.
	 * @param string              $address           Effective address.
	 * @param string              $location_url      Effective location URL.
	 * @param string              $event_url         Effective external event URL.
	 * @param string              $event_url_label   Effective external action label.
	 */
	public function __construct(
		public EventPresentation $series,
		public OccurrenceReadModel $occurrence,
		public string $title,
		public string $note,
		public int $featured_image_id,
		public string $venue,
		public string $address,
		public string $location_url,
		public string $event_url,
		public string $event_url_label
	) {}
}
