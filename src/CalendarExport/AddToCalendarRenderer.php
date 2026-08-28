<?php
/**
 * Shared semantic add-to-calendar rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;

/** Renders one optional action without JavaScript or host-specific event reads. */
final readonly class AddToCalendarRenderer {
	/**
	 * Create the shared renderer.
	 *
	 * @param CalendarExportSnapshotProvider $snapshots Public snapshot resolver.
	 * @param CalendarActionBuilder          $actions   Provider action builder.
	 * @param OccurrenceRouteController      $routes    Current exact occurrence route.
	 */
	public function __construct(
		private CalendarExportSnapshotProvider $snapshots = new CalendarExportSnapshotResolver(),
		private CalendarActionBuilder $actions = new CalendarActionBuilder(),
		private OccurrenceRouteController $routes = new OccurrenceRouteController()
	) {}

	/**
	 * Render the current event and prefer its already-validated occurrence leaf.
	 *
	 * @param int                  $event_id Canonical current event ID.
	 * @param AddToCalendarOptions $options  Shared presentation options.
	 */
	public function render_current( int $event_id, AddToCalendarOptions $options ): string {
		$context = $this->routes->current();
		$key     = null !== $context
			&& $context->series->event->ID === $event_id
			&& $context->occurrence->event_id === $event_id
			? $context->occurrence->public_key
			: null;

		return $this->render( $event_id, $key, $options );
	}

	/**
	 * Render an explicit public one-off event selection.
	 *
	 * @param int                  $event_id Canonical event ID.
	 * @param AddToCalendarOptions $options  Shared presentation options.
	 */
	public function render_public( int $event_id, AddToCalendarOptions $options ): string {
		return $this->render( $event_id, null, $options );
	}

	/**
	 * Render one exact resolved selection for shared adapters and tests.
	 *
	 * @param int                  $event_id   Canonical event ID.
	 * @param string|null          $public_key Exact occurrence key, or null.
	 * @param AddToCalendarOptions $options    Shared presentation options.
	 */
	public function render( int $event_id, ?string $public_key, AddToCalendarOptions $options ): string {
		if ( array() === $options->providers ) {
			return '';
		}

		$snapshot = $this->snapshots->resolve( $event_id, $public_key );

		if ( null === $snapshot ) {
			return '';
		}

		$actions = $this->actions->build( $snapshot, $options->providers );

		if ( array() === $actions ) {
			return '';
		}

		if ( 1 === count( $actions ) ) {
			return '<div class="wpse-add-to-calendar wpse-add-to-calendar-direct">'
				. $this->link( $actions[0] ) . '</div>';
		}

		$label = '' !== $options->label
			? $options->label
			: __( 'Add to calendar', 'mime-simple-events-calendar' );
		$list  = '<ul class="wpse-add-to-calendar-actions">';

		foreach ( $actions as $action ) {
			$list .= '<li>' . $this->link( $action ) . '</li>';
		}

		$list .= '</ul>';

		if ( AddToCalendarOptions::LAYOUT_LIST === $options->layout ) {
			return '<div class="wpse-add-to-calendar wpse-add-to-calendar-list">'
				. '<p class="wpse-add-to-calendar-label">' . esc_html( $label ) . '</p>'
				. $list . '</div>';
		}

		return '<details class="wpse-add-to-calendar wpse-add-to-calendar-dropdown">'
			. '<summary class="wpse-add-to-calendar-summary">' . esc_html( $label ) . '</summary>'
			. $list . '</details>';
	}

	/**
	 * Render one escaped provider link with complete isolation semantics.
	 *
	 * @param CalendarActionLink $action Validated provider action.
	 */
	private function link( CalendarActionLink $action ): string {
		$label = match ( $action->provider ) {
			CalendarProvider::ICS     => __( 'Download calendar file', 'mime-simple-events-calendar' ),
			CalendarProvider::GOOGLE  => __( 'Add to Google Calendar', 'mime-simple-events-calendar' ),
			CalendarProvider::OUTLOOK => __( 'Add to Outlook', 'mime-simple-events-calendar' ),
		};
		$attributes = $action->external
			? ' target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"'
			: ' download';

		return '<a class="wpse-add-to-calendar-action wpse-add-to-calendar-' . esc_attr( $action->provider->value )
			. '" href="' . esc_url( $action->url ) . '"' . $attributes . '>' . esc_html( $label ) . '</a>';
	}
}
