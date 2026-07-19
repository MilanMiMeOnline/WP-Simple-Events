<?php
/**
 * Event presentation snapshot creation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use WP_Post;

/**
 * Reads untrusted WordPress storage once and normalizes named presentation data.
 */
final readonly class EventPresentationFactory {
	/**
	 * Create the presentation factory.
	 *
	 * @param EventDateFormatter           $dates             Public date formatter.
	 * @param EventTimezoneDisplaySettings $timezone_settings Global timezone visibility.
	 * @param EventMetaSanitizer           $sanitizer         Stored-value normalizer.
	 */
	public function __construct(
		private EventDateFormatter $dates = new EventDateFormatter(),
		private EventTimezoneDisplaySettings $timezone_settings = new EventTimezoneDisplaySettings(),
		private EventMetaSanitizer $sanitizer = new EventMetaSanitizer()
	) {}

	/**
	 * Build one normalized presentation snapshot from a validated event post.
	 *
	 * @param WP_Post $event Event post object.
	 */
	public function create( WP_Post $event ): EventPresentation {
		$title = trim( get_the_title( $event ) );

		if ( '' === $title ) {
			$title = __( 'Untitled event', 'wp-simple-events' );
		}

		$permalink = get_permalink( $event );
		$status    = EventStatus::tryFrom(
			$this->sanitizer->status( $this->meta( $event->ID, EventMeta::STATUS ) )
		);

		return new EventPresentation(
			$event,
			$title,
			$permalink,
			has_post_thumbnail( $event ),
			$this->dates->format(
				$this->sanitizer->timestamp( $this->meta( $event->ID, EventMeta::START_UTC ) ),
				$this->sanitizer->timestamp( $this->meta( $event->ID, EventMeta::END_UTC ) ),
				$this->sanitizer->boolean( $this->meta( $event->ID, EventMeta::ALL_DAY ) ),
				$this->sanitizer->timezone( $this->meta( $event->ID, EventMeta::TIMEZONE ) ),
				$this->timezone_settings->enabled()
			),
			$status,
			$this->sanitizer->venue( $this->meta( $event->ID, EventMeta::VENUE ) ),
			$this->sanitizer->address( $this->meta( $event->ID, EventMeta::ADDRESS ) ),
			$this->sanitizer->url( $this->meta( $event->ID, EventMeta::LOCATION_URL ) ),
			$this->sanitizer->url( $this->meta( $event->ID, EventMeta::EVENT_URL ) ),
			$this->sanitizer->event_url_label( $this->meta( $event->ID, EventMeta::EVENT_URL_LABEL ) ),
			$this->terms( $event->ID, EventTaxonomies::CATEGORY ),
			$this->terms( $event->ID, EventTaxonomies::TAG )
		);
	}

	/**
	 * Read one untrusted stored metadata value.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Internal allowlisted metadata key.
	 */
	private function meta( int $event_id, string $meta_key ): mixed {
		return get_post_meta( $event_id, $meta_key, true );
	}

	/**
	 * Resolve public terms and destinations once per presentation snapshot.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $taxonomy Internal allowlisted taxonomy.
	 * @return EventTermPresentation[]
	 */
	private function terms( int $event_id, string $taxonomy ): array {
		$terms = get_the_terms( $event_id, $taxonomy );

		if ( false === $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$presentations = array();

		foreach ( $terms as $term ) {
			if ( '' === trim( $term->name ) ) {
				continue;
			}

			$url = get_term_link( $term, $taxonomy );

			if ( is_wp_error( $url ) || '' === $url ) {
				continue;
			}

			$presentations[] = new EventTermPresentation( $term->name, $url );
		}

		return $presentations;
	}
}
