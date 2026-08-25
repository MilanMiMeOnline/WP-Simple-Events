<?php
/**
 * Public occurrence REST response serialization.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Rest;

use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\EventTermPresentation;
use MiMe\WPSimpleEvents\Frontend\OccurrenceEventPresentationFactory;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;

/**
 * Converts one exact public occurrence into a bounded metadata-free resource.
 */
final readonly class OccurrenceRestSerializer {
	/** Public response schema version. */
	private const SCHEMA_VERSION = 1;

	/**
	 * Create the serializer.
	 *
	 * @param OccurrenceEventPresentationFactory $presentations Shared effective presentation adapter.
	 */
	public function __construct(
		private OccurrenceEventPresentationFactory $presentations = new OccurrenceEventPresentationFactory()
	) {}

	/**
	 * Serialize one exact occurrence or fail closed.
	 *
	 * @param OccurrencePresentationContext $context       Validated public occurrence context.
	 * @param string                        $canonical_url Exact occurrence canonical URL.
	 * @return array<string, mixed>|null
	 */
	public function serialize( OccurrencePresentationContext $context, string $canonical_url ): ?array {
		$presentation = $this->presentations->create( $context, $canonical_url );

		if ( null === $presentation || null === $presentation->date || null === $presentation->status ) {
			return null;
		}

		$range = $context->occurrence->date_range;

		return array(
			'schema_version'  => self::SCHEMA_VERSION,
			'event_id'        => $context->occurrence->event_id,
			'occurrence_key'  => $context->occurrence->public_key,
			'canonical_url'   => $presentation->permalink,
			'title'           => $presentation->title,
			'note'            => $presentation->note,
			'date'            => array(
				'start'          => $presentation->date->start_iso,
				'end'            => $presentation->date->end_iso,
				'start_local'    => $range->start_local(),
				'end_local'      => $range->end_local(),
				'all_day'        => $range->all_day(),
				'timezone'       => $range->timezone(),
				'label'          => $presentation->date->label,
				'timezone_label' => $presentation->date->timezone_label,
			),
			'status'          => $presentation->status->value,
			'featured_image'  => $this->featured_image( $presentation ),
			'location'        => array(
				'venue'   => $presentation->venue,
				'address' => $presentation->address,
				'url'     => $presentation->location_url,
			),
			'external_action' => $this->external_action( $presentation ),
			'categories'      => $this->terms( $presentation->categories ),
			'tags'            => $this->terms( $presentation->tags ),
		);
	}

	/**
	 * Resolve a public attachment URL without exposing an unavailable identifier.
	 *
	 * @param EventPresentation $presentation Effective public presentation.
	 * @return array{id: int, url: string}|null
	 */
	private function featured_image( EventPresentation $presentation ): ?array {
		if ( $presentation->featured_image_id <= 0 ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $presentation->featured_image_id, 'full' );
		$url = is_string( $url ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : '';

		return '' === $url
			? null
			: array(
				'id'  => $presentation->featured_image_id,
				'url' => $url,
			);
	}

	/**
	 * Return one complete external action or no action at all.
	 *
	 * @param EventPresentation $presentation Effective public presentation.
	 * @return array{url: string, label: string}|null
	 */
	private function external_action( EventPresentation $presentation ): ?array {
		if ( '' === $presentation->event_url ) {
			return null;
		}

		return array(
			'url'   => $presentation->event_url,
			'label' => '' !== $presentation->event_url_label
				? $presentation->event_url_label
				: __( 'More event information', 'mime-simple-events-calendar' ),
		);
	}

	/**
	 * Serialize only public term names and destinations.
	 *
	 * @param EventTermPresentation[] $terms Public taxonomy destinations.
	 * @return list<array{name: string, url: string}>
	 */
	private function terms( array $terms ): array {
		$serialized = array();

		foreach ( $terms as $term ) {
			$url = esc_url_raw( $term->url, array( 'http', 'https' ) );

			if ( '' === trim( $term->name ) || '' === $url ) {
				continue;
			}

			$serialized[] = array(
				'name' => $term->name,
				'url'  => $url,
			);
		}

		return $serialized;
	}
}
