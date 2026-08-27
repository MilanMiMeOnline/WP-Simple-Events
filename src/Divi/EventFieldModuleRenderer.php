<?php
/**
 * Host-neutral rendering for native Divi atomic event modules.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/** Resolves one safe event and delegates one allowlisted field to shared HTML. */
final readonly class EventFieldModuleRenderer {
	/**
	 * Stable module folder names mapped to internal renderer keys.
	 *
	 * @var array<string, string>
	 */
	public const MODULES = array(
		'event-featured-image'  => 'featured_image',
		'event-date-time'       => 'date_time',
		'event-status'          => 'status',
		'event-venue'           => 'venue',
		'event-address'         => 'address',
		'event-location-link'   => 'location_action',
		'event-content'         => 'content',
		'event-excerpt'         => 'excerpt',
		'external-event-action' => 'external_action',
		'event-categories'      => 'categories',
		'event-tags'            => 'tags',
	);

	/**
	 * Create the atomic rendering adapter.
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
	 * Render one known event field from normalized Divi attributes.
	 *
	 * @param string               $field            Internal allowlisted renderer key.
	 * @param array<string, mixed> $attrs            Divi module attributes.
	 * @param int|null             $current_event_id Optional current context for tests/adapters.
	 */
	public function render( string $field, array $attrs, ?int $current_event_id = null ): string {
		if ( ! in_array( $field, self::MODULES, true ) ) {
			return '';
		}

		$presentation = $this->presentation( $attrs, $current_event_id );

		if ( null === $presentation ) {
			return '';
		}

		return match ( $field ) {
			'featured_image' => $this->fields->featured_image(
				$presentation,
				DiviModuleSettings::choice(
					$attrs,
					'imageSize',
					array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ),
					'large'
				),
				DiviModuleSettings::toggle( $attrs, 'linkField' ),
				DiviModuleSettings::choice( $attrs, 'altMode', array( 'attachment', 'decorative' ), 'attachment' )
			),
			'date_time' => $this->fields->date_time(
				$presentation,
				DiviModuleSettings::toggle( $attrs, 'showLabel', true ),
				DiviModuleSettings::text( $attrs, 'label' )
			),
			'status' => $this->fields->status( $presentation ),
			'venue' => $this->fields->venue(
				$presentation,
				DiviModuleSettings::toggle( $attrs, 'showLabel', true ),
				DiviModuleSettings::text( $attrs, 'label' )
			),
			'address' => $this->fields->address( $presentation ),
			'location_action' => $this->fields->location_action(
				$presentation,
				DiviModuleSettings::text( $attrs, 'linkText' )
			),
			'content' => $this->fields->content( $presentation ),
			'excerpt' => $this->fields->excerpt( $presentation ),
			'external_action' => $this->fields->external_action(
				$presentation,
				DiviModuleSettings::text( $attrs, 'linkText' )
			),
			'categories' => $this->fields->categories(
				$presentation,
				DiviModuleSettings::toggle( $attrs, 'showLabel', true ),
				DiviModuleSettings::text( $attrs, 'label' )
			),
			'tags' => $this->fields->tags(
				$presentation,
				DiviModuleSettings::toggle( $attrs, 'showLabel', true ),
				DiviModuleSettings::text( $attrs, 'label' )
			),
		};
	}

	/**
	 * Resolve explicit selections strictly and current context safely.
	 *
	 * @param array<string, mixed> $attrs            Divi module attributes.
	 * @param int|null             $current_event_id Optional current context.
	 */
	private function presentation( array $attrs, ?int $current_event_id ): ?EventPresentation {
		$event_id = DiviModuleSettings::event_id( $attrs );

		return $event_id > 0
			? $this->events->resolve_public( $event_id )
			: $this->current->resolve( $current_event_id ?? get_queried_object_id() );
	}
}
