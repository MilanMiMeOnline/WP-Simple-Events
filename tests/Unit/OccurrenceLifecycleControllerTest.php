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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Verifies that only permanent event deletion reaches the derived store.
 */
#[CoversClass( OccurrenceLifecycleController::class )]
final class OccurrenceLifecycleControllerTest extends TestCase {
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

		self::assertSame( array(), $store->removed_event_ids );
	}
}
