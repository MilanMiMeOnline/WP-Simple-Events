<?php
/**
 * WordPress Site Health occurrence-index status.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceHealthMonitor;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceHealthStatus;

/**
 * Publishes the same privacy-safe occurrence state through Site Health.
 */
final readonly class OccurrenceSiteHealthController {
	public const TEST = 'wpse_occurrence_index';

	/**
	 * Create the Site Health adapter.
	 *
	 * @param OccurrenceHealthMonitor $health Shared health state machine.
	 */
	public function __construct(
		private OccurrenceHealthMonitor $health = new OccurrenceHealthMonitor()
	) {}

	/** Register the direct Site Health test filter. */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'add_test' ) );
	}

	/**
	 * Add one direct test without changing third-party tests.
	 *
	 * @param array<string, mixed> $tests Existing Site Health tests.
	 * @return array<string, mixed>
	 */
	public function add_test( array $tests ): array {
		$direct = isset( $tests['direct'] ) && is_array( $tests['direct'] )
			? $tests['direct']
			: array();

		$direct[ self::TEST ] = array(
			'label' => __( 'Event occurrence index', 'mime-simple-events-calendar' ),
			'test'  => array( $this, 'test' ),
		);

		$tests['direct'] = $direct;

		return $tests;
	}

	/**
	 * Return one privacy-safe Site Health result.
	 *
	 * @return array<string, mixed>
	 */
	public function test(): array {
		$status = $this->health->status();
		$result = match ( $status ) {
			OccurrenceHealthStatus::HEALTHY => array(
				'label'       => __( 'The event occurrence index is healthy', 'mime-simple-events-calendar' ),
				'status'      => 'good',
				'description' => __( 'Public events have a complete derived occurrence index.', 'mime-simple-events-calendar' ),
			),
			OccurrenceHealthStatus::BUILDING => array(
				'label'       => __( 'The event occurrence index is building', 'mime-simple-events-calendar' ),
				'status'      => 'recommended',
				'description' => __( 'Existing events are being indexed in bounded background batches.', 'mime-simple-events-calendar' ),
			),
			OccurrenceHealthStatus::REPAIR_NEEDED => array(
				'label'       => __( 'The event occurrence index needs repair', 'mime-simple-events-calendar' ),
				'status'      => 'recommended',
				'description' => __( 'At least one public event has no complete occurrence projection or is marked dirty.', 'mime-simple-events-calendar' ),
			),
			OccurrenceHealthStatus::UNAVAILABLE => array(
				'label'       => __( 'The event occurrence index is unavailable', 'mime-simple-events-calendar' ),
				'status'      => 'critical',
				'description' => __( 'The occurrence storage schema is not ready.', 'mime-simple-events-calendar' ),
			),
		};
		$actions = current_user_can( 'manage_options' )
			? '<p><a href="' . esc_url( EventSettingsPage::url() ) . '">' . esc_html__( 'Open event maintenance', 'mime-simple-events-calendar' ) . '</a></p>'
			: '';

		return array(
			'label'       => $result['label'],
			'status'      => $result['status'],
			'badge'       => array(
				'label' => __( 'Events', 'mime-simple-events-calendar' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $result['description'] ) . '</p>',
			'actions'     => $actions,
			'test'        => self::TEST,
		);
	}
}
