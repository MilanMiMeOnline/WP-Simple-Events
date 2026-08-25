<?php
/**
 * Effective occurrence presentation adapter.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Converts one exact occurrence context into the shared event presentation shape.
 */
final readonly class OccurrenceEventPresentationFactory {
	/**
	 * Create the adapter.
	 *
	 * @param EventDateFormatter           $dates             Public date formatter.
	 * @param EventTimezoneDisplaySettings $timezone_settings Global timezone visibility.
	 */
	public function __construct(
		private EventDateFormatter $dates = new EventDateFormatter(),
		private EventTimezoneDisplaySettings $timezone_settings = new EventTimezoneDisplaySettings()
	) {}

	/**
	 * Build an effective public occurrence presentation or fail closed.
	 *
	 * Body, excerpt and taxonomies deliberately remain owned by the series post.
	 *
	 * @param OccurrencePresentationContext $context       Exact active occurrence context.
	 * @param string                        $canonical_url Canonical occurrence leaf URL.
	 */
	public function create( OccurrencePresentationContext $context, string $canonical_url ): ?EventPresentation {
		$canonical_url = esc_url_raw( $canonical_url, array( 'http', 'https' ) );
		$range         = $context->occurrence->date_range;
		$date          = $this->dates->format(
			$range->start_utc(),
			$range->end_utc(),
			$range->all_day(),
			$range->timezone(),
			$this->timezone_settings->enabled()
		);

		if ( '' === $canonical_url || null === $date ) {
			return null;
		}

		$series = $context->series;

		return new EventPresentation(
			$series->event,
			$context->title,
			$canonical_url,
			$context->featured_image_id > 0,
			$date,
			$context->occurrence->status,
			$context->venue,
			$context->address,
			$context->location_url,
			$context->event_url,
			$context->event_url_label,
			$series->categories,
			$series->tags,
			$context->featured_image_id,
			$context->note
		);
	}
}
