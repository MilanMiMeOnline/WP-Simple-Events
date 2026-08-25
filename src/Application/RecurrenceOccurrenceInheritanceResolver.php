<?php
/**
 * WordPress-backed occurrence inheritance resolution.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Reads and normalizes the series fields an occurrence may override.
 */
final readonly class RecurrenceOccurrenceInheritanceResolver {
	/**
	 * Create the resolver.
	 *
	 * @param EventMetaSanitizer $sanitizer Stored-value normalization boundary.
	 */
	public function __construct( private EventMetaSanitizer $sanitizer = new EventMetaSanitizer() ) {}

	/**
	 * Resolve one safe series inheritance snapshot.
	 *
	 * @param int $event_id Canonical event post ID.
	 * @throws InvalidArgumentException When the event is unavailable.
	 */
	public function resolve( int $event_id ): RecurrenceOccurrenceInheritedFields {
		$event = get_post( $event_id );

		if ( ! $event instanceof WP_Post || EventPostType::POST_TYPE !== $event->post_type ) {
			throw new InvalidArgumentException( 'Occurrence inheritance requires an event post.' );
		}

		return new RecurrenceOccurrenceInheritedFields(
			sanitize_text_field( (string) $event->post_title ),
			'',
			max( 0, (int) get_post_thumbnail_id( $event ) ),
			$this->sanitizer->venue( $this->meta( $event_id, EventMeta::VENUE ) ),
			$this->sanitizer->address( $this->meta( $event_id, EventMeta::ADDRESS ) ),
			$this->sanitizer->url( $this->meta( $event_id, EventMeta::LOCATION_URL ) ),
			$this->sanitizer->url( $this->meta( $event_id, EventMeta::EVENT_URL ) ),
			$this->sanitizer->event_url_label( $this->meta( $event_id, EventMeta::EVENT_URL_LABEL ) )
		);
	}

	/**
	 * Read one allowlisted untrusted stored value.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Event meta key.
	 */
	private function meta( int $event_id, string $meta_key ): mixed {
		return get_post_meta( $event_id, $meta_key, true );
	}
}
