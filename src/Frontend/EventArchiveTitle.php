<?php
/**
 * Native event archive heading resolution.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use WP_Query;
use WP_Term;

/** Builds trusted plain-text headings for fixed event taxonomy routes. */
final readonly class EventArchiveTitle {
	/**
	 * Resolve one plain-text event taxonomy heading.
	 *
	 * WordPress archive titles intentionally contain decorative markup. The
	 * plugin renders its heading as escaped text, so it owns the wording instead
	 * of trusting or exposing that markup.
	 *
	 * @param WP_Query $query Current native event taxonomy query.
	 */
	public function taxonomy( WP_Query $query ): string {
		$taxonomy = $query->get( 'taxonomy' );

		if ( ! is_string( $taxonomy ) || ! in_array(
			$taxonomy,
			array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG ),
			true
		) ) {
			return __( 'Events', 'mime-simple-events-calendar' );
		}

		$term = $query->get_queried_object();

		if ( ! $term instanceof WP_Term || $term->taxonomy !== $taxonomy ) {
			return __( 'Events', 'mime-simple-events-calendar' );
		}

		$name = trim( wp_strip_all_tags( $term->name, true ) );

		if ( '' === $name ) {
			return __( 'Events', 'mime-simple-events-calendar' );
		}

		if ( EventTaxonomies::CATEGORY === $taxonomy ) {
			return sprintf(
				/* translators: %s: event category name. */
				__( 'Events in “%s”', 'mime-simple-events-calendar' ),
				$name
			);
		}

		return sprintf(
			/* translators: %s: event tag name. */
			__( 'Events tagged “%s”', 'mime-simple-events-calendar' ),
			$name
		);
	}
}
