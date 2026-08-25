<?php
/**
 * Occurrence projection publication lifecycle.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Application\RecurrenceScheduleOwnership;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Establishes one healthy bounded projection when an event becomes public.
 */
final readonly class OccurrencePublicationController {
	/**
	 * Create the publication controller.
	 *
	 * @param OccurrenceIndexRepairService     $repairer Canonical type-aware projection repair.
	 * @param RecurrenceScheduleOwnership      $ownership Protected recurrence ownership boundary.
	 * @param OccurrenceProjectionWindowPolicy $windows Production projection coverage policy.
	 */
	public function __construct(
		private OccurrenceIndexRepairService $repairer = new OccurrenceIndexRepairer(),
		private RecurrenceScheduleOwnership $ownership = new RecurrenceScheduleOwnership(),
		private OccurrenceProjectionWindowPolicy $windows = new OccurrenceProjectionWindowPolicy()
	) {}

	/** Register the canonical WordPress publication transition hook. */
	public function register(): void {
		add_action( 'transition_post_status', array( $this, 'transition' ), 10, 3 );
	}

	/**
	 * Repair only a newly public event from its canonical stored state.
	 *
	 * @param string  $new_status New WordPress post status.
	 * @param string  $old_status Previous WordPress post status.
	 * @param WP_Post $post       Transitioned post.
	 */
	public function transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $new_status
			|| 'publish' === $old_status
			|| EventPostType::POST_TYPE !== $post->post_type
			|| ! $this->requires_repair( $post->ID )
		) {
			return;
		}

		$this->repairer->repair( $post->ID, $new_status );
	}

	/**
	 * Avoid rebuilding a complete projection that already satisfies public reads.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function requires_repair( int $event_id ): bool {
		$generation = (int) get_post_meta( $event_id, EventMeta::ACTIVE_GENERATION, true );

		if ( $generation <= 0 || metadata_exists( 'post', $event_id, EventMeta::INDEX_DIRTY ) ) {
			return true;
		}

		if ( ! $this->ownership->owns( $event_id ) ) {
			return false;
		}

		$today               = wp_date( 'Y-m-d' );
		$coverage_from       = get_post_meta( $event_id, EventMeta::COVERAGE_FROM, true );
		$coverage_through    = get_post_meta( $event_id, EventMeta::COVERAGE_THROUGH, true );
		$coverage_generation = (int) get_post_meta( $event_id, EventMeta::COVERAGE_GENERATION, true );

		return ! is_string( $today )
			|| ! is_string( $coverage_from )
			|| ! is_string( $coverage_through )
			|| $coverage_generation !== $generation
			|| ! $this->windows->supports_public_reads( $coverage_from, $coverage_through, $today );
	}
}
