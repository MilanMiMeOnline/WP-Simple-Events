<?php
/**
 * WordPress event structured-data provider.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;

/**
 * Reads one public event through WordPress APIs and creates its schema graph.
 */
final class EventSchemaProvider {
	/**
	 * Create the provider.
	 *
	 * @param EventSchemaBuilder   $builder  Pure schema builder.
	 * @param EventContextResolver $contexts Public event presentation boundary.
	 */
	public function __construct(
		private readonly EventSchemaBuilder $builder = new EventSchemaBuilder(),
		private readonly EventContextResolver $contexts = new EventContextResolver()
	) {}

	/**
	 * Build schema only for a public, password-free event.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, mixed>|null
	 */
	public function provide( int $event_id ): ?array {
		return $this->provide_presentation( $this->contexts->resolve_public( $event_id ) );
	}

	/**
	 * Build schema from one already normalized public presentation.
	 *
	 * @param EventPresentation|null $presentation Public event or occurrence presentation.
	 * @return array<string, mixed>|null
	 */
	public function provide_presentation( ?EventPresentation $presentation ): ?array {
		if ( null === $presentation || null === $presentation->date || null === $presentation->status ) {
			return null;
		}

		$image_url = $presentation->featured_image_id > 0
			? wp_get_attachment_image_url( $presentation->featured_image_id, 'full' )
			: get_the_post_thumbnail_url( $presentation->event, 'full' );

		return $this->builder->build(
			new EventSchemaInput(
				name: wp_strip_all_tags( $presentation->title, true ),
				start_date: $presentation->date->start_iso,
				end_date: $presentation->date->end_iso,
				status: $presentation->status,
				url: $presentation->permalink,
				description: $this->description( $presentation ),
				image_url: is_string( $image_url ) ? $image_url : '',
				venue: $presentation->venue,
				address: $presentation->address
			)
		);
	}

	/**
	 * Build a bounded plain-text summary from visible post text.
	 *
	 * @param EventPresentation $presentation Public event or occurrence presentation.
	 */
	private function description( EventPresentation $presentation ): string {
		$event  = $presentation->event;
		$source = '' !== $presentation->note
			? $presentation->note
			: ( '' !== trim( $event->post_excerpt ) ? $event->post_excerpt : $event->post_content );
		$text   = trim( wp_strip_all_tags( strip_shortcodes( $source ), true ) );

		return '' === $text ? '' : wp_trim_words( $text, 55, '…' );
	}
}
