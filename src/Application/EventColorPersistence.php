<?php
/**
 * Canonical event color intent persistence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Content\EventCategoryMeta;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Domain\HexColor;

/** Replaces only the bounded metadata owned by the selected color mode. */
final class EventColorPersistence {
	/**
	 * Create the persistence boundary with shared metadata sanitization.
	 *
	 * @param EventMetaSanitizer $sanitizer Strict event metadata sanitizer.
	 */
	public function __construct( private readonly EventMetaSanitizer $sanitizer = new EventMetaSanitizer() ) {}

	/**
	 * Persist a verified native editor payload.
	 *
	 * @param int                  $post_id Event ID.
	 * @param array<string, mixed> $payload Verified native event payload.
	 */
	public function persist_admin( int $post_id, array $payload ): void {
		if ( ! array_key_exists( 'color_mode', $payload ) ) {
			return;
		}

		$this->persist(
			$post_id,
			$payload['color_mode'],
			$payload['event_color'] ?? '',
			$payload['display_category_id'] ?? 0
		);
	}

	/**
	 * Persist color fields only when a REST request explicitly carries them.
	 *
	 * @param int                  $post_id Event ID.
	 * @param array<string, mixed> $meta    Untrusted REST meta object.
	 */
	public function persist_rest( int $post_id, array $meta ): void {
		$keys = array( EventMeta::COLOR_MODE, EventMeta::COLOR, EventMeta::DISPLAY_CATEGORY );

		if ( array() === array_intersect( $keys, array_keys( $meta ) ) ) {
			return;
		}

		$this->persist(
			$post_id,
			$meta[ EventMeta::COLOR_MODE ] ?? EventColorMode::AUTOMATIC->value,
			$meta[ EventMeta::COLOR ] ?? '',
			$meta[ EventMeta::DISPLAY_CATEGORY ] ?? 0
		);
	}

	/**
	 * Replace the complete mutually exclusive color selection.
	 *
	 * @param int   $post_id       Event ID.
	 * @param mixed $mode_value    Untrusted color mode.
	 * @param mixed $color_value   Untrusted custom color.
	 * @param mixed $category_value Untrusted category ID.
	 */
	private function persist( int $post_id, mixed $mode_value, mixed $color_value, mixed $category_value ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		$mode = EventColorMode::tryFrom( $this->sanitizer->color_mode( $mode_value ) ) ?? EventColorMode::FALLBACK;

		if ( EventColorMode::AUTOMATIC === $mode ) {
			$this->clear( $post_id );
			return;
		}

		update_post_meta( $post_id, EventMeta::COLOR_MODE, $mode->value );

		if ( EventColorMode::CUSTOM === $mode ) {
			$color = $this->sanitizer->color( $color_value );

			if ( '' === $color ) {
				delete_post_meta( $post_id, EventMeta::COLOR );
			} else {
				update_post_meta( $post_id, EventMeta::COLOR, $color );
			}

			delete_post_meta( $post_id, EventMeta::DISPLAY_CATEGORY );
			return;
		}

		delete_post_meta( $post_id, EventMeta::COLOR );

		if ( EventColorMode::CATEGORY === $mode ) {
			$category_id = $this->sanitizer->term_id( $category_value );

			if ( $this->is_assigned_colored_category( $post_id, $category_id ) ) {
				update_post_meta( $post_id, EventMeta::DISPLAY_CATEGORY, $category_id );
				return;
			}
		}

		delete_post_meta( $post_id, EventMeta::DISPLAY_CATEGORY );
	}

	/**
	 * Whether an explicit source still belongs to this event and has a valid color.
	 *
	 * @param int $post_id     Event ID.
	 * @param int $category_id Proposed assigned category ID.
	 */
	private function is_assigned_colored_category( int $post_id, int $category_id ): bool {
		if ( $category_id <= 0 ) {
			return false;
		}

		$term_ids = wp_get_post_terms( $post_id, EventTaxonomies::CATEGORY, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $term_ids ) || ! in_array( $category_id, array_map( 'intval', $term_ids ), true ) ) {
			return false;
		}

		return '' !== HexColor::normalize( get_term_meta( $category_id, EventCategoryMeta::COLOR, true ) );
	}

	/**
	 * Remove every optional color key for the migration-free automatic mode.
	 *
	 * @param int $post_id Event ID.
	 */
	private function clear( int $post_id ): void {
		delete_post_meta( $post_id, EventMeta::COLOR_MODE );
		delete_post_meta( $post_id, EventMeta::COLOR );
		delete_post_meta( $post_id, EventMeta::DISPLAY_CATEGORY );
	}
}
