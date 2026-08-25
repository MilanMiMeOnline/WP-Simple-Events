<?php
/**
 * WordPress occurrence projection persistence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;

/**
 * Writes only complete, inactive generations before switching one metadata marker.
 */
final class WordPressOccurrenceProjectionStore implements OccurrenceProjectionStore {
	/**
	 * Create the WordPress adapter.
	 *
	 * @param OccurrenceTable          $table  Projection table lifecycle and name.
	 * @param GenerationTokenGenerator $tokens Collision-resistant generation tokens.
	 */
	public function __construct(
		private readonly OccurrenceTable $table = new OccurrenceTable(),
		private readonly GenerationTokenGenerator $tokens = new GenerationTokenGenerator()
	) {}

	/**
	 * Return or atomically allocate the immutable series UID.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function series_uid( int $event_id ): ?string {
		if ( $event_id <= 0 ) {
			return null;
		}

		$sanitizer = new EventMetaSanitizer();
		$stored    = $sanitizer->uuid( get_post_meta( $event_id, EventMeta::SERIES_UID, true ) );

		if ( '' !== $stored ) {
			return $stored;
		}

		$candidate = strtolower( wp_generate_uuid4() );

		if ( '' === $sanitizer->uuid( $candidate ) ) {
			return null;
		}

		if ( add_post_meta( $event_id, EventMeta::SERIES_UID, $candidate, true ) ) {
			return $candidate;
		}

		$winner = $sanitizer->uuid( get_post_meta( $event_id, EventMeta::SERIES_UID, true ) );

		return '' === $winner ? null : $winner;
	}

	/**
	 * Allocate a non-sequential token so concurrent builds cannot share rows.
	 */
	public function new_generation(): int {
		return $this->tokens->generate();
	}

	/**
	 * Insert every row before switching the active generation marker.
	 *
	 * Stale generations are deliberately retained for bounded later cleanup. An
	 * immediate cleanup could delete a concurrently built generation before its
	 * request activates it.
	 *
	 * @param int                               $event_id    Canonical event post ID.
	 * @param int                               $generation Complete generation token.
	 * @param EventOccurrence[]                 $occurrences Complete occurrence set; may be empty.
	 * @param OccurrenceProjectionCoverage|null $coverage Recurring coverage, or null for one-off state.
	 */
	public function replace(
		int $event_id,
		int $generation,
		array $occurrences,
		?OccurrenceProjectionCoverage $coverage = null
	): bool {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb
			|| $event_id <= 0
			|| $generation <= 0
			|| ! $this->mark_dirty( $event_id )
		) {
			return false;
		}

		foreach ( $occurrences as $occurrence ) {
			if ( $event_id !== $occurrence->event_id
				|| $generation !== $occurrence->generation
			) {
				$this->delete_generation( $event_id, $generation );

				return false;
			}

			$row                = $occurrence->projection_row();
			$row['created_utc'] = time();
			$inserted           = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- This is the dedicated adapter for the plugin-owned projection table.
				$this->table->table_name(),
				$row,
				array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d' )
			);

			if ( false === $inserted ) {
				$this->delete_generation( $event_id, $generation );

				return false;
			}
		}

		if ( ! $this->replace_coverage( $event_id, $generation, $coverage ) ) {
			$this->delete_generation( $event_id, $generation );

			return false;
		}

		if ( false === update_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, $generation )
			&& ( new EventMetaSanitizer() )->generation(
				get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true )
			) !== $generation
		) {
			$this->delete_generation( $event_id, $generation );

			return false;
		}

		return $this->finish_replacement( $event_id, $generation, $coverage );
	}

	/**
	 * Remove all derived rows and the active marker for one canonical event.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	public function remove( int $event_id ): bool {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb || $event_id <= 0 || ! $this->mark_dirty( $event_id ) ) {
			return false;
		}

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This mutation belongs to the dedicated adapter for the rebuildable plugin-owned projection; a cached deletion result would be incorrect.
			$this->table->table_name(),
			array( 'event_id' => $event_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return false;
		}

		delete_post_meta( $event_id, EventMeta::ACTIVE_GENERATION );
		delete_post_meta( $event_id, EventMeta::COVERAGE_FROM );
		delete_post_meta( $event_id, EventMeta::COVERAGE_THROUGH );
		delete_post_meta( $event_id, EventMeta::COVERAGE_GENERATION );
		delete_post_meta( $event_id, EventMeta::INDEX_DIRTY );

		$removed = ! metadata_exists( 'post', $event_id, EventMeta::ACTIVE_GENERATION )
			&& ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_FROM )
			&& ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_THROUGH )
			&& ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_GENERATION )
			&& ! metadata_exists( 'post', $event_id, EventMeta::INDEX_DIRTY );

		if ( ! $removed ) {
			$this->mark_dirty( $event_id );
		}

		return $removed;
	}

	/**
	 * Make every projection mutation fail closed before touching derived state.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	private function mark_dirty( int $event_id ): bool {
		update_post_meta( $event_id, EventMeta::INDEX_DIRTY, true );

		return ( new EventMetaSanitizer() )->boolean(
			get_post_meta( $event_id, EventMeta::INDEX_DIRTY, true )
		);
	}

	/**
	 * Clear the repair marker only while activation and coverage still agree.
	 *
	 * The second check closes the window in which another rebuild could mutate
	 * the active generation after this request's first successful check.
	 *
	 * @param int                               $event_id   Canonical event post ID.
	 * @param int                               $generation Complete generation token.
	 * @param OccurrenceProjectionCoverage|null $coverage Complete recurring coverage.
	 */
	private function finish_replacement(
		int $event_id,
		int $generation,
		?OccurrenceProjectionCoverage $coverage
	): bool {
		if ( ! $this->generation_is_active( $event_id, $generation )
			|| ! $this->coverage_matches( $event_id, $generation, $coverage )
		) {
			return false;
		}

		delete_post_meta( $event_id, EventMeta::INDEX_DIRTY );

		$healthy = ! metadata_exists( 'post', $event_id, EventMeta::INDEX_DIRTY )
			&& $this->generation_is_active( $event_id, $generation )
			&& $this->coverage_matches( $event_id, $generation, $coverage );

		if ( ! $healthy ) {
			$this->mark_dirty( $event_id );
		}

		return $healthy;
	}

	/**
	 * Verify that this request's complete generation remains active.
	 *
	 * @param int $event_id   Canonical event post ID.
	 * @param int $generation Complete generation token.
	 * @phpstan-impure Reads mutable WordPress metadata during a concurrency check.
	 */
	private function generation_is_active( int $event_id, int $generation ): bool {
		return ( new EventMetaSanitizer() )->generation(
			get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true )
		) === $generation;
	}

	/**
	 * Replace or clear recurrence-only coverage and verify the stored values.
	 *
	 * @param int                               $event_id   Canonical event post ID.
	 * @param int                               $generation Complete generation token.
	 * @param OccurrenceProjectionCoverage|null $coverage Complete recurring coverage.
	 * @phpstan-impure Reads mutable WordPress metadata during a concurrency check.
	 */
	private function replace_coverage(
		int $event_id,
		int $generation,
		?OccurrenceProjectionCoverage $coverage
	): bool {
		if ( null === $coverage ) {
			delete_post_meta( $event_id, EventMeta::COVERAGE_FROM );
			delete_post_meta( $event_id, EventMeta::COVERAGE_THROUGH );
			delete_post_meta( $event_id, EventMeta::COVERAGE_GENERATION );

			return ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_FROM )
				&& ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_THROUGH )
				&& ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_GENERATION );
		}

		update_post_meta( $event_id, EventMeta::COVERAGE_FROM, $coverage->from_date() );
		update_post_meta( $event_id, EventMeta::COVERAGE_THROUGH, $coverage->through_date() );
		update_post_meta( $event_id, EventMeta::COVERAGE_GENERATION, $generation );

		return $this->coverage_matches( $event_id, $generation, $coverage );
	}

	/**
	 * Verify that coverage still belongs to the generation this request built.
	 *
	 * @param int                               $event_id   Canonical event post ID.
	 * @param int                               $generation Complete generation token.
	 * @param OccurrenceProjectionCoverage|null $coverage Complete recurring coverage.
	 * @phpstan-impure Reads mutable WordPress metadata during a concurrency check.
	 */
	private function coverage_matches(
		int $event_id,
		int $generation,
		?OccurrenceProjectionCoverage $coverage
	): bool {
		if ( null === $coverage ) {
			return ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_FROM )
				&& ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_THROUGH )
				&& ! metadata_exists( 'post', $event_id, EventMeta::COVERAGE_GENERATION );
		}

		return $coverage->from_date() === get_post_meta( $event_id, EventMeta::COVERAGE_FROM, true )
			&& $coverage->through_date() === get_post_meta( $event_id, EventMeta::COVERAGE_THROUGH, true )
			&& ( new EventMetaSanitizer() )->generation(
				get_post_meta( $event_id, EventMeta::COVERAGE_GENERATION, true )
			) === $generation;
	}

	/**
	 * Delete one incomplete inactive generation after a failed replacement.
	 *
	 * @param int $event_id   Canonical event post ID.
	 * @param int $generation Incomplete generation token.
	 */
	private function delete_generation( int $event_id, int $generation ): void {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return;
		}

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Failure cleanup mutates the rebuildable plugin-owned projection and cannot use a cached result.
			$this->table->table_name(),
			array(
				'event_id'   => $event_id,
				'generation' => $generation,
			),
			array( '%d', '%d' )
		);
	}
}
