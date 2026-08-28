<?php
/**
 * Prepared public event colors.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventCategoryMeta;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventColorPresentation;
use MiMe\WPSimpleEvents\Domain\EventColorResolver;
use MiMe\WPSimpleEvents\Domain\HexColor;
use WP_Term;

/** Primes WordPress caches once and resolves a bounded canonical event set. */
final readonly class EventColorCollection {
	private const MAX_EVENTS = 500;

	/**
	 * Create the prepared collection adapter.
	 *
	 * @param EventColorResolver $resolver Pure deterministic resolver.
	 */
	public function __construct( private EventColorResolver $resolver = new EventColorResolver() ) {}

	/**
	 * Prepare colors keyed by canonical event ID.
	 *
	 * @param int[] $event_ids Event post IDs from one bounded response.
	 * @return array<int, EventColorPresentation>
	 */
	public function prepare( array $event_ids ): array {
		$event_ids = $this->ids( $event_ids, self::MAX_EVENTS );

		if ( array() === $event_ids ) {
			return array();
		}

		update_meta_cache( 'post', $event_ids );
		update_object_term_cache( $event_ids, EventPostType::POST_TYPE );

		$terms_by_event = array();
		$term_ids       = array();

		foreach ( $event_ids as $event_id ) {
			$terms                       = get_the_terms( $event_id, EventTaxonomies::CATEGORY );
			$terms                       = false === $terms || is_wp_error( $terms ) ? array() : array_values( $terms );
			$terms_by_event[ $event_id ] = $terms;

			foreach ( $terms as $term ) {
				if ( $term->term_id > 0 ) {
					$term_ids[] = $term->term_id;
				}
			}
		}

		$term_ids = $this->ids( $term_ids, 1000 );

		if ( array() !== $term_ids ) {
			update_meta_cache( 'term', $term_ids );
		}

		$colors = array();

		foreach ( $event_ids as $event_id ) {
			$category_colors = $this->colors_for_terms( $terms_by_event[ $event_id ] );
			$resolved        = $this->resolver->resolve(
				get_post_meta( $event_id, EventMeta::COLOR_MODE, true ),
				get_post_meta( $event_id, EventMeta::COLOR, true ),
				get_post_meta( $event_id, EventMeta::DISPLAY_CATEGORY, true ),
				$category_colors,
				''
			);

			if ( null !== $resolved ) {
				$colors[ $event_id ] = $resolved;
			}
		}

		return $colors;
	}

	/**
	 * Return normalized colors keyed by term ID. Callers may prime term metadata first.
	 *
	 * @param WP_Term[] $terms Public event categories.
	 * @return array<int, string>
	 */
	public function colors_for_terms( array $terms ): array {
		$colors = array();

		foreach ( array_slice( $terms, 0, 100 ) as $term ) {
			if ( $term->term_id <= 0 ) {
				continue;
			}

			$color = HexColor::normalize( get_term_meta( $term->term_id, EventCategoryMeta::COLOR, true ) );

			if ( '' !== $color ) {
				$colors[ $term->term_id ] = $color;
			}
		}

		return $colors;
	}

	/**
	 * Prime and return colors for one bounded public term collection.
	 *
	 * @param WP_Term[] $terms Public event categories.
	 * @return array<int, string>
	 */
	public function prepare_term_colors( array $terms ): array {
		$term_ids = array_map( static fn ( WP_Term $term ): int => $term->term_id, array_slice( $terms, 0, 100 ) );
		$term_ids = $this->ids( $term_ids, 100 );

		if ( array() !== $term_ids ) {
			update_meta_cache( 'term', $term_ids );
		}

		return $this->colors_for_terms( $terms );
	}

	/**
	 * Normalize and deduplicate positive IDs.
	 *
	 * @param mixed[] $values Candidate object IDs.
	 * @param int     $limit  Maximum number of input values.
	 * @return int[]
	 */
	private function ids( array $values, int $limit ): array {
		$ids = array();

		foreach ( array_slice( $values, 0, $limit ) as $value ) {
			if ( is_int( $value ) && $value > 0 ) {
				$ids[ $value ] = $value;
			}
		}

		return array_values( $ids );
	}
}
