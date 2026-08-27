<?php
/**
 * Tests for permanent occurrence cleanup.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceLifecycleController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceProjectionStore;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Verifies that only permanent event deletion reaches the derived store.
 */
#[CoversClass( OccurrenceLifecycleController::class )]
final class OccurrenceLifecycleControllerTest extends TestCase {
	/** The lifecycle owns cleanup immediately before and verifies it after deletion. */
	public function test_registers_both_permanent_deletion_guards(): void {
		HookRecorder::reset();
		( new OccurrenceLifecycleController( new FakeOccurrenceProjectionStore() ) )->register();

		self::assertIsCallable( HookRecorder::action( 'before_delete_post' ) );
		self::assertIsCallable( HookRecorder::action( 'deleted_post' ) );
	}

	/**
	 * Deleting an event removes all of its derived generations.
	 */
	public function test_event_deletion_removes_projection(): void {
		$store = new FakeOccurrenceProjectionStore();
		$post  = new WP_Post(
			array(
				'ID'        => 42,
				'post_type' => EventPostType::POST_TYPE,
			)
		);

		( new OccurrenceLifecycleController( $store ) )->delete( 42, $post );

		self::assertSame( array( 42 ), $store->removed_event_ids );
	}

	/** A completed event deletion performs a second table-only purge. */
	public function test_deleted_event_verifies_projection_cleanup(): void {
		$store = new FakeOccurrenceProjectionStore();
		$post  = new WP_Post(
			array(
				'ID'        => 42,
				'post_type' => EventPostType::POST_TYPE,
			)
		);

		( new OccurrenceLifecycleController( $store ) )->verify_deleted( 42, $post );

		self::assertSame( array( 42 ), $store->purged_event_ids );
	}

	/**
	 * Unrelated posts never touch event occurrence rows.
	 */
	public function test_unrelated_post_is_ignored(): void {
		$store = new FakeOccurrenceProjectionStore();
		$post  = new WP_Post(
			array(
				'ID'        => 9,
				'post_type' => 'post',
			)
		);

		( new OccurrenceLifecycleController( $store ) )->delete( 9, $post );
		( new OccurrenceLifecycleController( $store ) )->verify_deleted( 9, $post );

		self::assertSame( array(), $store->removed_event_ids );
		self::assertSame( array(), $store->purged_event_ids );
	}
}
