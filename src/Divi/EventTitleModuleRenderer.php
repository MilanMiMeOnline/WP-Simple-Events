<?php
/**
 * Host-neutral Event Title module rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;

/** Resolves and renders the title without depending on Divi runtime classes. */
final readonly class EventTitleModuleRenderer {
	/**
	 * Create the shared presentation adapter.
	 *
	 * @param EventContextResolver             $events  Explicit public event resolver.
	 * @param CurrentEventPresentationResolver $current Current event/occurrence resolver.
	 * @param EventFieldRenderer               $fields  Shared semantic field renderer.
	 */
	public function __construct(
		private EventContextResolver $events,
		private CurrentEventPresentationResolver $current,
		private EventFieldRenderer $fields
	) {}

	/**
	 * Render a safe event title from normalized Divi attributes.
	 *
	 * @param array<string, mixed> $attrs            Divi module attributes.
	 * @param int|null             $current_event_id Optional explicit current context for tests/adapters.
	 */
	public function render( array $attrs, ?int $current_event_id = null ): string {
		$event_id     = DiviModuleSettings::event_id( $attrs );
		$presentation = $event_id > 0
			? $this->events->resolve_public( $event_id )
			: $this->current->resolve( $current_event_id ?? get_queried_object_id() );

		return null !== $presentation
			? $this->fields->title(
				$presentation,
				DiviModuleSettings::heading( $attrs ),
				'',
				DiviModuleSettings::link_title( $attrs )
			)
			: '';
	}
}
