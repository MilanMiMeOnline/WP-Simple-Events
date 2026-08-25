<?php
/**
 * Current event or occurrence presentation resolution.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;

/**
 * Resolves the current host context without changing explicit series selections.
 */
final readonly class CurrentEventPresentationResolver {
	/**
	 * Create the request-shared current-context adapter.
	 *
	 * @param EventContextResolver               $events      Ordinary event context resolver.
	 * @param OccurrenceRouteController          $occurrences Validated occurrence route context.
	 * @param OccurrenceEventPresentationFactory $factory     Effective occurrence adapter.
	 */
	public function __construct(
		private EventContextResolver $events = new EventContextResolver(),
		private OccurrenceRouteController $occurrences = new OccurrenceRouteController(),
		private OccurrenceEventPresentationFactory $factory = new OccurrenceEventPresentationFactory()
	) {}

	/**
	 * Resolve one current event, preferring its exact active occurrence leaf.
	 *
	 * @param int $event_id Current event post ID.
	 */
	public function resolve( int $event_id ): ?EventPresentation {
		$occurrence = $this->occurrences->current();

		if ( null === $occurrence
			|| $occurrence->series->event->ID !== $event_id
			|| $occurrence->occurrence->event_id !== $event_id
		) {
			return $this->events->resolve_current( $event_id );
		}

		return $this->factory->create(
			$occurrence,
			$this->occurrences->canonical_url( $occurrence )
		);
	}
}
