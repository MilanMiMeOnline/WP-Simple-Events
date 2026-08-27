<?php
/**
 * Permission-safe Divi Visual Builder data.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Query\PublicEventOptions;
use WP_Post;

/** Supplies only public choices and the authorized current editor context. */
final readonly class DiviEditorDataProvider {
	/**
	 * Create the bounded editor data provider.
	 *
	 * @param PublicEventOptions               $options Bounded public event choices.
	 * @param EventContextResolver             $events  Explicit public event resolver.
	 * @param CurrentEventPresentationResolver $current Current editor context resolver.
	 */
	public function __construct(
		private PublicEventOptions $options,
		private EventContextResolver $events,
		private CurrentEventPresentationResolver $current
	) {}

	/**
	 * Build localized Visual Builder data without private cross-event leakage.
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {
		$events = array();

		foreach ( $this->options->options() as $event_id => $title ) {
			$presentation = $this->events->resolve_public( (int) $event_id );

			if ( null !== $presentation ) {
				$events[ (string) $event_id ] = $this->event_data( $presentation, $title );
			}
		}

		$editor_post_id = max( 0, get_queried_object_id() );
		$editor_post    = $editor_post_id > 0 ? get_post( $editor_post_id ) : null;
		$can_edit       = $editor_post instanceof WP_Post && current_user_can( 'edit_post', $editor_post_id );
		$current        = $can_edit ? $this->current->resolve( $editor_post_id ) : null;

		return array(
			'current'         => null !== $current ? $this->event_data( $current, $current->title ) : null,
			'events'          => $events,
			'editorPostId'    => $can_edit ? $editor_post_id : 0,
			'taxonomyOptions' => array(
				'categories' => $this->term_options( EventTaxonomies::CATEGORY ),
				'tags'       => $this->term_options( EventTaxonomies::TAG ),
			),
			'translations'    => DiviEditorTranslations::all(),
			'labels'          => array(
				'currentEvent'    => __( 'Current event', 'mime-simple-events-calendar' ),
				'noEvent'         => __( 'No event is available in this preview context.', 'mime-simple-events-calendar' ),
				'dateTime'        => __( 'Date and time:', 'mime-simple-events-calendar' ),
				'location'        => __( 'Location:', 'mime-simple-events-calendar' ),
				'viewLocation'    => __( 'View location', 'mime-simple-events-calendar' ),
				'moreInformation' => __( 'More event information', 'mime-simple-events-calendar' ),
				'categories'      => __( 'Categories:', 'mime-simple-events-calendar' ),
				'tags'            => __( 'Tags:', 'mime-simple-events-calendar' ),
				'cancelled'       => __( 'Cancelled', 'mime-simple-events-calendar' ),
				'postponed'       => __( 'Postponed', 'mime-simple-events-calendar' ),
				'contentPreview'  => __( 'Saved event content preview', 'mime-simple-events-calendar' ),
				'excerptPreview'  => __( 'Saved event excerpt preview', 'mime-simple-events-calendar' ),
				'previewLoading'  => __( 'Updating event preview…', 'mime-simple-events-calendar' ),
				'previewError'    => __( 'The event preview could not be loaded. Your saved page remains unchanged.', 'mime-simple-events-calendar' ),
				'previewEmpty'    => __( 'No matching public event content is available for this preview.', 'mime-simple-events-calendar' ),
			),
		);
	}

	/**
	 * Build bounded public taxonomy choices for Divi checkbox controls.
	 *
	 * @param string $taxonomy Allowlisted event taxonomy.
	 * @return list<array{value: string, label: string}>
	 */
	private function term_options( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 100,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			$slug = sanitize_title( $term->slug );

			if ( '' !== $slug ) {
				$options[] = array(
					'value' => $slug,
					'label' => sanitize_text_field( $term->name ),
				);
			}
		}

		return $options;
	}

	/**
	 * Normalize the exact public fields needed by the title preview.
	 *
	 * @param EventPresentation $event Resolved presentation.
	 * @param string            $title Sanitized choice label.
	 * @return array<string, mixed>
	 */
	private function event_data( EventPresentation $event, string $title ): array {
		$date   = $event->date;
		$status = null !== $event->status ? $event->status->value : '';

		return array(
			'id'             => $event->event->ID,
			'title'          => sanitize_text_field( $title ),
			'url'            => esc_url_raw( $event->permalink ),
			'date'           => null !== $date
				? array(
					'label'         => sanitize_text_field( $date->label ),
					'startIso'      => sanitize_text_field( $date->start_iso ),
					'endIso'        => sanitize_text_field( $date->end_iso ),
					'timezoneLabel' => sanitize_text_field( $date->timezone_label ),
				)
				: null,
			'status'         => in_array( $status, array( 'cancelled', 'postponed' ), true ) ? $status : '',
			'venue'          => sanitize_text_field( $event->venue ),
			'address'        => sanitize_textarea_field( $event->address ),
			'locationUrl'    => esc_url_raw( $event->location_url ),
			'eventUrl'       => esc_url_raw( $event->event_url ),
			'eventUrlLabel'  => sanitize_text_field( $event->event_url_label ),
			'categories'     => $this->terms( $event->categories ),
			'tags'           => $this->terms( $event->tags ),
			'images'         => $this->images( $event ),
			'contentPreview' => $this->text_preview( $event->event->post_content ),
			'excerptPreview' => $this->text_preview( get_the_excerpt( $event->event ) ),
		);
	}

	/**
	 * Provide public term labels and destinations without taxonomy internals.
	 *
	 * @param \MiMe\WPSimpleEvents\Frontend\EventTermPresentation[] $terms Public term presentations.
	 * @return array<int, array{name: string, url: string}>
	 */
	private function terms( array $terms ): array {
		return array_map(
			static fn ( $term ): array => array(
				'name' => sanitize_text_field( $term->name ),
				'url'  => esc_url_raw( $term->url ),
			),
			$terms
		);
	}

	/**
	 * Provide bounded public image sources for editor-only visual parity.
	 *
	 * @param EventPresentation $event Resolved event presentation.
	 * @return array<string, array{url: string, alt: string}>
	 */
	private function images( EventPresentation $event ): array {
		if ( ! $event->has_featured_image || $event->featured_image_id < 1 ) {
			return array();
		}

		$images = array();
		$alt    = sanitize_text_field( get_post_meta( $event->featured_image_id, '_wp_attachment_image_alt', true ) );

		foreach ( array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ) as $size ) {
			$url = wp_get_attachment_image_url( $event->featured_image_id, $size );

			if ( is_string( $url ) && '' !== $url ) {
				$images[ $size ] = array(
					'url' => esc_url_raw( $url ),
					'alt' => $alt,
				);
			}
		}

		return $images;
	}

	/**
	 * Create a small plain-text editor preview without executing saved content.
	 *
	 * @param string $content Saved event content or excerpt.
	 */
	private function text_preview( string $content ): string {
		$text = sanitize_text_field( wp_strip_all_tags( strip_shortcodes( $content ), true ) );

		return wp_trim_words( $text, 55, '…' );
	}
}
