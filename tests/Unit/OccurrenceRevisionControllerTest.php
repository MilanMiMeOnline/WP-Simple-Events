<?php
/**
 * Tests for occurrence projection revision invalidation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceRevisionController;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves restored canonical events cannot retain a falsely healthy projection.
 */
#[CoversClass( OccurrenceRevisionController::class )]
final class OccurrenceRevisionControllerTest extends TestCase {
	/**
	 * Reset deterministic hooks and WordPress state.
	 */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/**
	 * The controller registers the exact core revision lifecycle hook.
	 */
	public function test_registers_revision_restore_hook(): void {
		( new OccurrenceRevisionController() )->register();

		self::assertIsCallable( HookRecorder::action( 'wp_restore_post_revision' ) );
	}

	/**
	 * Restoring an event marks only its derived occurrence projection dirty.
	 */
	public function test_event_restore_marks_projection_dirty(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);

		( new OccurrenceRevisionController() )->after_restore( 42, 99 );

		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * Restoring ordinary WordPress content does not create plugin metadata.
	 */
	public function test_non_event_restore_is_ignored(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => 'post',
				)
			)
		);

		( new OccurrenceRevisionController() )->after_restore( 42, 99 );

		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}
}
