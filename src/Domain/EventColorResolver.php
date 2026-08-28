<?php
/**
 * Deterministic event color resolution.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/** Resolves explicit event intent without WordPress globals or term ordering. */
final class EventColorResolver {
	/**
	 * Resolve one event's bounded color presentation.
	 *
	 * @param mixed                    $stored_mode              Stored mode or absent value.
	 * @param mixed                    $event_color              Optional custom event color.
	 * @param mixed                    $selected_category_id     Explicit display category ID.
	 * @param array<int|string, mixed> $assigned_category_colors Assigned term ID to color map.
	 * @param mixed                    $component_fallback       Optional builder/component fallback.
	 */
	public function resolve(
		mixed $stored_mode,
		mixed $event_color,
		mixed $selected_category_id,
		array $assigned_category_colors,
		mixed $component_fallback
	): ?EventColorPresentation {
		$mode     = EventColorMode::from_stored( $stored_mode );
		$fallback = $this->presentation( $component_fallback, EventColorSource::FALLBACK );

		if ( null === $mode || EventColorMode::FALLBACK === $mode ) {
			return $fallback;
		}

		if ( EventColorMode::CUSTOM === $mode ) {
			return $this->presentation( $event_color, EventColorSource::CUSTOM ) ?? $fallback;
		}

		$category_colors = $this->category_colors( $assigned_category_colors );

		if ( EventColorMode::CATEGORY === $mode ) {
			$category_id = $this->term_id( $selected_category_id );

			if ( null === $category_id || ! isset( $category_colors[ $category_id ] ) ) {
				return $fallback;
			}

			return $this->presentation(
				$category_colors[ $category_id ],
				EventColorSource::CATEGORY,
				$category_id
			) ?? $fallback;
		}

		$distinct = array_values( array_unique( array_values( $category_colors ) ) );

		if ( 1 !== count( $distinct ) ) {
			return $fallback;
		}

		$matching_ids = array_keys( $category_colors, $distinct[0], true );
		$category_id  = null;

		if ( 1 === count( $matching_ids ) ) {
			$category_id = $matching_ids[0];
		}

		return $this->presentation(
			$distinct[0],
			EventColorSource::CATEGORY,
			$category_id
		) ?? $fallback;
	}

	/**
	 * Normalize assigned category colors by positive term ID.
	 *
	 * @param array<int|string, mixed> $values Raw term-color map.
	 * @return array<int, string>
	 */
	private function category_colors( array $values ): array {
		$colors = array();

		foreach ( $values as $term_id => $value ) {
			$term_id = $this->term_id( $term_id );
			$color   = HexColor::normalize( $value );

			if ( null !== $term_id && '' !== $color ) {
				$colors[ $term_id ] = $color;
			}
		}

		ksort( $colors, SORT_NUMERIC );

		return $colors;
	}

	/**
	 * Return one positive bounded WordPress object ID.
	 *
	 * @param mixed $value Untrusted object ID.
	 */
	private function term_id( mixed $value ): ?int {
		if ( is_string( $value ) && 1 !== preg_match( '/^[1-9]\d*$/D', $value ) ) {
			return null;
		}

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$value = (int) $value;

		return 0 < $value && 2_147_483_647 >= $value ? $value : null;
	}

	/**
	 * Build one result only after normalizing both public color values.
	 *
	 * @param mixed            $background  Untrusted background.
	 * @param EventColorSource $source      Intended normalized source.
	 * @param int|null         $category_id Exact category source when known.
	 */
	private function presentation(
		mixed $background,
		EventColorSource $source,
		?int $category_id = null
	): ?EventColorPresentation {
		$background = HexColor::normalize( $background );

		if ( '' === $background ) {
			return null;
		}

		return new EventColorPresentation(
			$background,
			HexColor::contrast_text( $background ),
			$source,
			$category_id
		);
	}
}
