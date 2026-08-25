<?php
/**
 * Occurrence-aware WordPress document metadata.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use WP_Post;

/**
 * Keeps the core document title and canonical bound to one exact occurrence.
 */
final readonly class OccurrenceDocumentController {
	/**
	 * Create the document adapter around the shared request route.
	 *
	 * @param OccurrenceRouteController $occurrences Current exact occurrence route.
	 */
	public function __construct( private OccurrenceRouteController $occurrences ) {}

	/** Register core document filters only while occurrence routing is enabled. */
	public function register(): void {
		add_filter( 'document_title_parts', array( $this, 'title_parts' ) );
		add_filter( 'get_canonical_url', array( $this, 'canonical_url' ), 10, 2 );
	}

	/**
	 * Replace only the singular title part for an exact occurrence leaf.
	 *
	 * @param array<string, string> $parts Existing core document-title parts.
	 * @return array<string, string>
	 */
	public function title_parts( array $parts ): array {
		$context = $this->occurrences->current();

		if ( null !== $context ) {
			$parts['title'] = $context->title;
		}

		return $parts;
	}

	/**
	 * Replace only the matching series post's core canonical URL.
	 *
	 * @param string       $canonical_url Existing canonical URL.
	 * @param WP_Post|null $post          Canonical post candidate.
	 */
	public function canonical_url( string $canonical_url, ?WP_Post $post = null ): string {
		$context = $this->occurrences->current();

		if ( null === $context || ( null !== $post && $post->ID !== $context->series->event->ID ) ) {
			return $canonical_url;
		}

		$occurrence_url = $this->occurrences->canonical_url( $context );

		return '' !== $occurrence_url ? $occurrence_url : $canonical_url;
	}
}
