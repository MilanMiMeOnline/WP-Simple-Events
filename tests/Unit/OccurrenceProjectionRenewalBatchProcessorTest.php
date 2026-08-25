<?php
/**
 * Tests for bounded recurring projection renewal.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionRenewalBatchProcessor;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceIndexRepairer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/** Proves renewal selection remains recurring-only, bounded and offset-aware. */
#[CoversClass( OccurrenceProjectionRenewalBatchProcessor::class )]
final class OccurrenceProjectionRenewalBatchProcessorTest extends TestCase {
	/** Reset deterministic posts. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** One batch never exceeds 25 public password-free recurring candidates. */
	public function test_query_is_bounded_and_recurring_only(): void {
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

		$result    = ( new OccurrenceProjectionRenewalBatchProcessor( new FakeOccurrenceIndexRepairer() ) )->process( 4 );
		$arguments = WordPressState::last_get_posts_arguments();

		self::assertSame( 25, $result->processed );
		self::assertTrue( $result->has_more );
		self::assertSame( 4, $arguments['offset'] );
		self::assertFalse( $arguments['has_password'] );
		self::assertSame( EventMeta::RECURRENCE, $arguments['meta_query'][1][0]['key'] );
	}
}
