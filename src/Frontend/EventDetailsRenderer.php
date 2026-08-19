<?php
/**
 * Complete public event-details rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Composes the named presentation fields for native, shortcode and Elementor use.
 */
final class EventDetailsRenderer {
	/**
	 * Event IDs currently rendering across all instances in this request.
	 *
	 * @var array<int, true>
	 */
	private static array $rendering = array();

	/**
	 * Request-local access-aware event resolver.
	 *
	 * @var EventContextResolver
	 */
	private readonly EventContextResolver $contexts;

	/**
	 * Named semantic event-field renderer.
	 *
	 * @var EventFieldRenderer
	 */
	private readonly EventFieldRenderer $fields;

	/**
	 * Create the renderer while preserving the existing formatter dependencies.
	 *
	 * @param EventDateFormatter           $date_formatter    Public event date formatter.
	 * @param EventTimezoneDisplaySettings $timezone_settings Global timezone-display setting.
	 * @param EventContextResolver|null    $contexts          Shared access-aware context resolver.
	 * @param EventFieldRenderer|null      $fields            Shared named field renderer.
	 */
	public function __construct(
		EventDateFormatter $date_formatter = new EventDateFormatter(),
		EventTimezoneDisplaySettings $timezone_settings = new EventTimezoneDisplaySettings(),
		?EventContextResolver $contexts = null,
		?EventFieldRenderer $fields = null
	) {
		$this->contexts = $contexts ?? new EventContextResolver(
			new EventPresentationFactory( $date_formatter, $timezone_settings )
		);
		$this->fields   = $fields ?? new EventFieldRenderer();
	}

	/**
	 * Render a current page/template event, including authorized previews.
	 *
	 * @param int                 $event_id Current event post ID.
	 * @param EventDetailsOptions $options  Bounded presentation choices.
	 */
	public function render( int $event_id, EventDetailsOptions $options = new EventDetailsOptions() ): string {
		return $this->render_presentation( $this->contexts->resolve_current( $event_id ), $options );
	}

	/**
	 * Render an explicitly selected public, password-free event.
	 *
	 * @param int                 $event_id Explicit public event ID.
	 * @param EventDetailsOptions $options  Bounded presentation choices.
	 */
	public function render_public( int $event_id, EventDetailsOptions $options = new EventDetailsOptions() ): string {
		return $this->render_presentation( $this->contexts->resolve_public( $event_id ), $options );
	}

	/**
	 * Render a complete event in the established presentation order.
	 *
	 * @param EventPresentation|null $presentation Resolved event presentation.
	 * @param EventDetailsOptions    $options      Bounded presentation choices.
	 */
	private function render_presentation( ?EventPresentation $presentation, EventDetailsOptions $options ): string {
		if ( null === $presentation ) {
			return '';
		}

		$event = $presentation->event;

		if ( post_password_required( $event ) ) {
			// WordPress builds and contextually escapes the complete form. Applying
			// wp_kses_post() here would remove the required form and input elements.
			return get_the_password_form( $event );
		}

		if ( isset( self::$rendering[ $event->ID ] ) ) {
			return '';
		}

		self::$rendering[ $event->ID ] = true;
		$instance                      = RenderInstanceIds::next( RenderInstanceIds::EVENT_DETAILS );

		try {
			return $this->event( $presentation, $options, 'wpse-event-title-' . $event->ID . '-' . $instance );
		} finally {
			unset( self::$rendering[ $event->ID ] );
		}
	}

	/**
	 * Compose the existing complete event markup from named field fragments.
	 *
	 * @param EventPresentation   $presentation Resolved event presentation.
	 * @param EventDetailsOptions $options    Bounded presentation choices.
	 * @param string              $title_id   Unique composite heading ID.
	 */
	private function event( EventPresentation $presentation, EventDetailsOptions $options, string $title_id ): string {
		$title      = $options->show_title ? $this->fields->title( $presentation, $options->heading_level, $title_id ) : '';
		$image      = $options->show_image ? $this->fields->featured_image( $presentation ) : '';
		$date       = $options->show_date ? $this->fields->date_time( $presentation, true, $options->date_label ) : '';
		$status     = $options->show_status ? $this->fields->status( $presentation ) : '';
		$venue      = $options->show_location ? $this->fields->venue( $presentation, true, $options->venue_label ) : '';
		$address    = $options->show_location ? $this->fields->address( $presentation ) : '';
		$location   = $options->show_location ? $this->fields->location_action( $presentation, $options->location_label ) : '';
		$content    = $options->show_content ? $this->fields->content( $presentation ) : '';
		$action     = $options->show_action ? $this->fields->external_action( $presentation, $options->action_label ) : '';
		$categories = $options->show_terms ? $this->fields->categories( $presentation, true, $options->categories_label ) : '';
		$tags       = $options->show_terms ? $this->fields->tags( $presentation, true, $options->tags_label ) : '';
		$summary    = $this->summary( $date, $status, $venue, $address, $location );
		$terms      = $this->terms( $categories, $tags );
		$header     = '' !== $title || '' !== $image
			? '<header class="wpse-single-event-header">' . $title . $image . '</header>'
			: '';

		if ( '' === $header . $summary . $content . $action . $terms ) {
			return '';
		}

		$label = '' !== $title
			? ' aria-labelledby="' . esc_attr( $title_id ) . '"'
			: ' aria-label="' . esc_attr( $presentation->title ) . '"';

		return '<article class="wpse-single-event"' . $label . '>'
			. $header
			. $summary
			. $content
			. $action
			. $terms
			. '</article>';
	}

	/**
	 * Preserve the established summary and nested location wrappers.
	 *
	 * @param string $date     Date/time fragment.
	 * @param string $status   Exceptional-status fragment.
	 * @param string $venue    Venue fragment.
	 * @param string $address  Address fragment.
	 * @param string $location Location-action fragment.
	 */
	private function summary(
		string $date,
		string $status,
		string $venue,
		string $address,
		string $location
	): string {
		$location_group = '' !== $venue || '' !== $address || '' !== $location
			? '<div class="wpse-event-location">' . $venue . $address . $location . '</div>'
			: '';

		return '' !== $date || '' !== $status || '' !== $location_group
			? '<div class="wpse-event-summary">' . $date . $status . $location_group . '</div>'
			: '';
	}

	/**
	 * Preserve the established combined taxonomy footer.
	 *
	 * @param string $categories Category fragment.
	 * @param string $tags       Tag fragment.
	 */
	private function terms( string $categories, string $tags ): string {
		return '' !== $categories || '' !== $tags
			? '<footer class="wpse-event-taxonomies">' . $categories . $tags . '</footer>'
			: '';
	}
}
