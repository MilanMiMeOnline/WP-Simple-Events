<?php
/**
 * Tests for the secured event duplication controller.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventDuplicateController;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( EventDuplicateController::class )]
/**
 * Verifies hook scope and capability-gated nonce links.
 */
final class EventDuplicateControllerTest extends TestCase {
	/**
	 * Reset hook and authorization state.
	 */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/**
	 * The row action and state-changing endpoint use explicit admin hooks.
	 */
	public function test_registers_duplication_hooks(): void {
		$controller = new EventDuplicateController();

		$controller->register();

		self::assertSame( array( $controller, 'row_actions' ), HookRecorder::action( 'post_row_actions' ) );
		self::assertSame(
			array( $controller, 'duplicate' ),
			HookRecorder::action( 'admin_action_' . EventDuplicateController::ACTION )
		);
		self::assertSame( array( $controller, 'render_notice' ), HookRecorder::action( 'admin_notices' ) );
	}

	/**
	 * Authorized editors receive an event-specific nonce URL.
	 */
	public function test_adds_nonce_protected_row_action_for_authorized_event(): void {
		WordPressState::allow_current_user( true );
		$event   = new WP_Post(
			array(
				'ID'          => 77,
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$actions = ( new EventDuplicateController() )->row_actions( array( 'edit' => 'Edit' ), $event );

		self::assertArrayHasKey( 'wpse_duplicate', $actions );
		self::assertStringContainsString( 'action=wpse_duplicate_event', $actions['wpse_duplicate'] );
		self::assertStringContainsString( 'post=77', $actions['wpse_duplicate'] );
		self::assertStringContainsString( '_wpnonce=nonce-wpse_duplicate_event_77', $actions['wpse_duplicate'] );
	}

	/**
	 * Missing permission and unrelated posts never expose the state-changing URL.
	 */
	public function test_hides_row_action_without_permission_or_for_other_post_types(): void {
		$controller = new EventDuplicateController();
		$event      = new WP_Post(
			array(
				'ID'        => 77,
				'post_type' => EventPostType::POST_TYPE,
			)
		);

		self::assertSame( array(), $controller->row_actions( array(), $event ) );

		WordPressState::allow_current_user( true );
		$blog_post = new WP_Post(
			array(
				'ID'        => 78,
				'post_type' => 'post',
			)
		);
		self::assertSame( array(), $controller->row_actions( array(), $blog_post ) );
	}
}
