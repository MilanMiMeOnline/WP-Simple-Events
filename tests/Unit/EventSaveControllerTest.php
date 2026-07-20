<?php
/**
 * Tests for native event publication guarding.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventMetaBox;
use MiMe\WPSimpleEvents\Admin\EventSaveController;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Verifies nonce, capability and invalid-publication behavior at the WP boundary.
 */
#[CoversClass( EventSaveController::class )]
final class EventSaveControllerTest extends TestCase {
	/**
	 * Reset request and WordPress test state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		$_POST = array();
	}

	/**
	 * Remove request state after each test.
	 */
	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * A publish attempt without dates becomes a draft with a stable error code.
	 */
	public function test_invalid_publication_is_downgraded_and_reported(): void {
		WordPressState::allow_current_user( true );
		$_POST      = array(
			EventMetaBox::NONCE_NAME => 'valid-event-nonce',
			'wpse_event'             => $this->payload(
				array(
					'start_date' => '',
					'start_time' => '',
					'end_date'   => '',
					'end_time'   => '',
				)
			),
		);
		$controller = new EventSaveController();

		$data = $controller->guard_publication(
			array(
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
			),
			array( 'ID' => 42 )
		);

		self::assertSame( 'draft', $data['post_status'] );
		self::assertStringContainsString(
			'wpse_validation=missing_start_date',
			$controller->add_errors_to_redirect( 'https://example.com/wp-admin/post.php' )
		);
	}

	/**
	 * Missing capability cannot bypass the stored publication invariant.
	 */
	public function test_unauthorized_request_cannot_publish_incomplete_stored_data(): void {
		WordPressState::allow_current_user( false );
		$_POST      = array(
			EventMetaBox::NONCE_NAME => 'valid-event-nonce',
			'wpse_event'             => $this->payload( array( 'start_date' => '' ) ),
		);
		$controller = new EventSaveController();
		$data       = $controller->guard_publication(
			array(
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
			),
			array( 'ID' => 42 )
		);

		self::assertSame( 'draft', $data['post_status'] );
		self::assertStringContainsString( 'missing_start_date', $controller->add_errors_to_redirect( 'https://example.com/edit' ) );
	}

	/**
	 * A bad nonce cannot bypass the stored publication invariant.
	 */
	public function test_invalid_nonce_cannot_publish_incomplete_stored_data(): void {
		WordPressState::allow_current_user( true );
		$_POST      = array(
			EventMetaBox::NONCE_NAME => 'invalid',
			'wpse_event'             => $this->payload(),
		);
		$controller = new EventSaveController();
		$data       = $controller->guard_publication(
			array(
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
			),
			array( 'ID' => 42 )
		);

		self::assertSame( 'draft', $data['post_status'] );
	}

	/**
	 * Complete and authorized event data keeps the requested publication state.
	 */
	public function test_valid_publication_keeps_publish_status(): void {
		WordPressState::allow_current_user( true );
		$_POST      = array(
			EventMetaBox::NONCE_NAME => 'valid-event-nonce',
			'wpse_event'             => $this->payload(),
		);
		$controller = new EventSaveController();
		$data       = $controller->guard_publication(
			array(
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
			),
			array( 'ID' => 42 )
		);

		self::assertSame( 'publish', $data['post_status'] );
	}

	/**
	 * A nonce- and capability-authorized native save persists the sanitized label.
	 */
	public function test_valid_native_save_persists_sanitized_event_url_label(): void {
		WordPressState::allow_current_user( true );
		$_POST = array(
			EventMetaBox::NONCE_NAME => 'valid-event-nonce',
			'wpse_event'             => $this->payload(
				array(
					'event_url'       => 'https://example.com/parking',
					'event_url_label' => '<b>Parking plan</b>',
				)
			),
		);

		( new EventSaveController() )->save(
			42,
			new WP_Post(
				array(
					'ID'          => 42,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
				)
			),
			true
		);

		self::assertSame( 'Parking plan', WordPressState::post_meta( 42, EventMeta::EVENT_URL_LABEL ) );
	}

	/**
	 * Quick Edit may publish an already complete stored event without panel data.
	 */
	public function test_status_only_write_uses_complete_stored_event(): void {
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, '2026-07-20T09:30:00' );
		WordPressState::update_post_meta( 42, EventMeta::END_LOCAL, '2026-07-20T11:00:00' );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( 42, EventMeta::STATUS, 'scheduled' );

		$controller = new EventSaveController();
		$data       = $controller->guard_publication(
			array(
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
			),
			array( 'ID' => 42 )
		);

		self::assertSame( 'publish', $data['post_status'] );
	}

	/**
	 * Only Plugin Check may publish its disposable event fixture for runtime inspection.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_official_plugin_check_command_can_publish_its_disposable_fixture(): void {
		define( 'WP_CLI', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Official WP-CLI runtime constant required by this isolated compatibility test.
		define( 'WP_PLUGIN_CHECK_VERSION', '2.0.0' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Official Plugin Check runtime constant required by this isolated compatibility test.
		$controller      = new EventSaveController();
		$_SERVER['argv'] = array( 'wp', 'post', 'create', 'plugin', 'check' );
		$event_command   = $controller->guard_publication(
			array(
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
			),
			array()
		);

		self::assertSame( 'draft', $event_command['post_status'] );

		$_SERVER['argv'] = array( 'wp', '--quiet', 'plugin', 'check', 'wp-simple-events' );

		$plugin_check = $controller->guard_publication(
			array(
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
			),
			array()
		);

		self::assertSame( 'publish', $plugin_check['post_status'] );
	}

	/**
	 * Build a valid native meta box payload with selected overrides.
	 *
	 * @param array<string, string> $overrides Selected payload overrides.
	 * @return array<string, string>
	 */
	private function payload( array $overrides = array() ): array {
		return array_merge(
			array(
				'start_date'      => '2026-07-20',
				'start_time'      => '09:30',
				'end_date'        => '2026-07-20',
				'end_time'        => '11:00',
				'venue'           => 'Town Hall',
				'address'         => 'Main Street 1',
				'location_url'    => '',
				'event_url'       => '',
				'event_url_label' => '',
				'status'          => 'scheduled',
			),
			$overrides
		);
	}
}
