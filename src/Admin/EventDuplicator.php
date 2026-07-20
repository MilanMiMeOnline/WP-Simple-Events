<?php
/**
 * WordPress event duplication persistence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Error;
use WP_Post;

/**
 * Creates an atomic allowlisted event draft copy.
 */
final class EventDuplicator {
	/**
	 * Create the duplication service.
	 *
	 * @param EventDuplicatePlan $plan Explicit copy policy.
	 */
	public function __construct( private readonly EventDuplicatePlan $plan = new EventDuplicatePlan() ) {}

	/**
	 * Duplicate one event into a new draft.
	 *
	 * @param int $source_id Source event ID.
	 * @return int|WP_Error New event ID or an actionable error.
	 */
	public function duplicate( int $source_id ): int|WP_Error {
		$source = get_post( $source_id );

		if ( ! $source instanceof WP_Post || EventPostType::POST_TYPE !== $source->post_type ) {
			return new WP_Error(
				'wpse_duplicate_invalid_source',
				__( 'The source event could not be found.', 'simple-events-by-mime' )
			);
		}

		$new_id = wp_insert_post( $this->plan->post_data( $source ), true );

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		foreach ( $this->plan->meta_keys() as $meta_key ) {
			if ( ! metadata_exists( 'post', $source_id, $meta_key ) ) {
				continue;
			}

			if ( false === update_post_meta( $new_id, $meta_key, get_post_meta( $source_id, $meta_key, true ) ) ) {
				return $this->rollback( $new_id, 'wpse_duplicate_meta_failed' );
			}
		}

		if ( false === update_post_meta( $new_id, EventMeta::DATES_NEED_REVIEW, true ) ) {
			return $this->rollback( $new_id, 'wpse_duplicate_review_flag_failed' );
		}

		foreach ( $this->plan->taxonomies() as $taxonomy ) {
			$term_ids = wp_get_post_terms( $source_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $term_ids ) ) {
				$this->delete_draft( $new_id );
				return $term_ids;
			}

			$term_ids = array_map( 'intval', $term_ids );
			$assigned = wp_set_object_terms( $new_id, $term_ids, $taxonomy, false );

			if ( is_wp_error( $assigned ) ) {
				$this->delete_draft( $new_id );
				return $assigned;
			}
		}

		return $new_id;
	}

	/**
	 * Roll back a partially created draft and return a stable error.
	 *
	 * @param int    $post_id New draft ID.
	 * @param string $code    Stable error code.
	 */
	private function rollback( int $post_id, string $code ): WP_Error {
		$this->delete_draft( $post_id );

		return new WP_Error(
			$code,
			__( 'The event copy could not be completed safely.', 'simple-events-by-mime' )
		);
	}

	/**
	 * Permanently remove only the newly created partial draft.
	 *
	 * @param int $post_id New draft ID.
	 */
	private function delete_draft( int $post_id ): void {
		wp_delete_post( $post_id, true );
	}
}
