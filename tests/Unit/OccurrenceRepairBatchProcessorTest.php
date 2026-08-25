<?php
/**
 * Tests for bounded administrator occurrence repair.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceRepairBatchProcessor;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceIndexRepairer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves selection, accounting and continuation remain bounded.
 */
#[CoversClass( OccurrenceRepairBatchProcessor::class )]
final class OccurrenceRepairBatchProcessorTest extends TestCase {
	/** Reset deterministic posts. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** Repair is public-only, password-safe, bounded and offset-aware. */
	public function test_query_is_bounded_and_uses_unresolved_offset(): void {
		foreach ( range( 1, 30 ) as $event_id ) {
			WordPressState::add_post(
				new WP_Post(
					array(
						'ID'          => $event_id,
						'post_type'   => EventPostType::POST_TYPE,
						'post_status' => 'publish',
					)
				)
			);
		}

		$result    = ( new OccurrenceRepairBatchProcessor( new FakeOccurrenceIndexRepairer() ) )->process( 3 );
		$arguments = WordPressState::last_get_posts_arguments();

		self::assertSame( OccurrenceRepairBatchProcessor::BATCH_SIZE, $result->processed );
		self::assertTrue( $result->has_more );
		self::assertSame( 'publish', $arguments['post_status'] );
		self::assertFalse( $arguments['has_password'] );
		self::assertSame( 3, $arguments['offset'] );
		self::assertSame( EventMeta::ACTIVE_GENERATION, $arguments['meta_query'][1][0]['key'] );
		self::assertSame( EventMeta::INDEX_DIRTY, $arguments['meta_query'][1][1]['key'] );
	}

	/** Invalid and failed candidates are reported separately from repaired rows. */
	public function test_reports_unresolved_candidates_without_hiding_successes(): void {
		foreach ( range( 1, 3 ) as $event_id ) {
			WordPressState::add_post(
				new WP_Post(
					array(
						'ID'          => $event_id,
						'post_type'   => EventPostType::POST_TYPE,
						'post_status' => 'publish',
					)
				)
			);
		}
		$repairer = new FakeOccurrenceIndexRepairer(
			array(
				1 => OccurrenceIndexRepairStatus::INVALID,
				2 => OccurrenceIndexRepairStatus::FAILED,
			)
		);

		$result = ( new OccurrenceRepairBatchProcessor( $repairer ) )->process();

		self::assertSame( 3, $result->processed );
		self::assertSame( 1, $result->indexed );
		self::assertSame( 1, $result->skipped_invalid );
		self::assertSame( 1, $result->failed );
		self::assertFalse( $result->has_more );
	}
}
