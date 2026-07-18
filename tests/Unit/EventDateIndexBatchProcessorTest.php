<?php
/**
 * Tests for bounded event date-index maintenance batches.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Maintenance\EventDateIndexBatchProcessor;
use MiMe\WPSimpleEvents\Maintenance\EventDateIndexBatchResult;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( EventDateIndexBatchProcessor::class )]
#[CoversClass( EventDateIndexBatchResult::class )]
/**
 * Verifies deterministic pagination and invalid-event accounting.
 */
final class EventDateIndexBatchProcessorTest extends TestCase {
	/**
	 * Reset deterministic posts and metadata.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Fifty-one events cross the fixed batch boundary without an unbounded query.
	 */
	public function test_processes_events_in_fixed_pages(): void {
		foreach ( range( 1, 51 ) as $post_id ) {
			WordPressState::add_post(
				new WP_Post(
					array(
						'ID'          => $post_id,
						'post_type'   => EventPostType::POST_TYPE,
						'post_status' => 'draft',
					)
				)
			);
		}

		$processor = new EventDateIndexBatchProcessor();
		$first     = $processor->process( 1 );
		$second    = $processor->process( 2 );

		self::assertSame( 50, $first->processed );
		self::assertTrue( $first->has_more );
		self::assertSame( 2, $first->next_page );
		self::assertSame( 1, $second->processed );
		self::assertFalse( $second->has_more );
	}

	/**
	 * Invalid published data is counted but never aborts the remaining batch.
	 */
	public function test_counts_invalid_events_as_skipped(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 60,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
				)
			)
		);

		$result = ( new EventDateIndexBatchProcessor() )->process( 1 );

		self::assertSame( 1, $result->processed );
		self::assertSame( 1, $result->skipped );
		self::assertSame( 0, $result->changed );
		self::assertSame( 0, $result->failed );
	}
}
