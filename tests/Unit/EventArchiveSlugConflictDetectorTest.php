<?php
/**
 * Tests for event archive/page slug conflict detection.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Routing\EventArchiveSlugConflictDetector;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( EventArchiveSlugConflictDetector::class )]
/**
 * Verifies precise page/archive path conflict detection.
 */
final class EventArchiveSlugConflictDetectorTest extends TestCase {
	/**
	 * Reset deterministic posts.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * A live page occupying the root archive path is a conflict.
	 */
	public function test_detects_an_existing_non_trashed_page_at_the_archive_slug(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 91,
					'post_type'   => 'page',
					'post_name'   => 'events',
					'post_status' => 'publish',
				)
			)
		);

		self::assertTrue( ( new EventArchiveSlugConflictDetector() )->has_page_conflict( 'events' ) );
	}

	/**
	 * Missing, trashed and non-page records do not raise false warnings.
	 */
	public function test_ignores_absent_trashed_and_non_page_content(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 92,
					'post_type'   => 'page',
					'post_name'   => 'trashed-events',
					'post_status' => 'trash',
				)
			)
		);
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 93,
					'post_type'   => 'post',
					'post_name'   => 'events',
					'post_status' => 'publish',
				)
			)
		);
		$detector = new EventArchiveSlugConflictDetector();

		self::assertFalse( $detector->has_page_conflict( 'missing' ) );
		self::assertFalse( $detector->has_page_conflict( 'trashed-events' ) );
		self::assertFalse( $detector->has_page_conflict( 'events' ) );
	}
}
