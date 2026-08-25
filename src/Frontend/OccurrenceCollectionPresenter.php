<?php
/**
 * Occurrence collection presentation bridge.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Occurrence\OccurrencePage;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceSource;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteUrlBuilder;

/**
 * Converts a complete public occurrence page without changing its cardinality.
 */
final readonly class OccurrenceCollectionPresenter {
	/**
	 * Create the bridge.
	 *
	 * @param EventContextResolver                    $events      Public series resolver.
	 * @param ProjectedOccurrencePresentationProvider $recurring   Recurring inheritance resolver.
	 * @param OccurrenceEventPresentationFactory      $factory     Shared presentation adapter.
	 * @param OccurrenceRouteUrlBuilder               $urls        Canonical leaf URL builder.
	 */
	public function __construct(
		private EventContextResolver $events = new EventContextResolver(),
		private ProjectedOccurrencePresentationProvider $recurring = new OccurrencePresentationResolver(),
		private OccurrenceEventPresentationFactory $factory = new OccurrenceEventPresentationFactory(),
		private OccurrenceRouteUrlBuilder $urls = new OccurrenceRouteUrlBuilder()
	) {}

	/**
	 * Present one complete occurrence page, or fail closed without partial output.
	 *
	 * @param OccurrencePage $page Exact authorized occurrence result page.
	 */
	public function present( OccurrencePage $page ): ?OccurrenceCollectionPage {
		$items = array();

		foreach ( $page->occurrences as $occurrence ) {
			$context = OccurrenceSource::ONE_OFF === $occurrence->source
				? $this->one_off_context( $occurrence )
				: $this->recurring->resolve_projected( $occurrence );

			if ( null === $context || ! $this->matches( $context, $occurrence ) ) {
				return null;
			}

			$url          = OccurrenceSource::ONE_OFF === $occurrence->source
				? $context->series->permalink
				: $this->urls->build( $context->series->permalink, $occurrence->public_key );
			$presentation = $this->factory->create( $context, $url );

			if ( null === $presentation ) {
				return null;
			}

			$items[] = new OccurrenceCollectionItem( $occurrence, $presentation );
		}

		return new OccurrenceCollectionPage( $items, $page->total, $page->total_pages );
	}

	/**
	 * Build one inherited one-off context from its authoritative projection row.
	 *
	 * @param OccurrenceReadModel $occurrence Active public one-off projection row.
	 */
	private function one_off_context( OccurrenceReadModel $occurrence ): ?OccurrencePresentationContext {
		$series = $this->events->resolve_public( $occurrence->event_id );

		if ( null === $series || 'one-off' !== $occurrence->recurrence_id ) {
			return null;
		}

		return new OccurrencePresentationContext(
			$series,
			$occurrence,
			$series->title,
			'',
			$series->featured_image_id,
			$series->venue,
			$series->address,
			$series->location_url,
			$series->event_url,
			$series->event_url_label
		);
	}

	/**
	 * Prove that a provider did not substitute a different row or parent.
	 *
	 * @param OccurrencePresentationContext $context    Resolved effective presentation context.
	 * @param OccurrenceReadModel           $occurrence Expected active projection row.
	 */
	private function matches(
		OccurrencePresentationContext $context,
		OccurrenceReadModel $occurrence
	): bool {
		$resolved = $context->occurrence;

		return $context->series->event->ID === $occurrence->event_id
			&& $resolved->event_id === $occurrence->event_id
			&& $resolved->generation === $occurrence->generation
			&& $resolved->recurrence_id === $occurrence->recurrence_id
			&& hash_equals( $resolved->public_key, $occurrence->public_key );
	}
}
