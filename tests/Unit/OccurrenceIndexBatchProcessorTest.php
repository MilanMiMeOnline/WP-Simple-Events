<?php
/**
 * Tests for bounded occurrence migration batches.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\EventPublicationPolicy;
use MiMe\WPSimpleEvents\Application\EventValidator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexBatchProcessor;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexBatchResult;
use MiMe\WPSimpleEvents\Occurrence\OneOffOccurrenceIndexRepairer;
use MiMe\WPSimpleEvents\Tests\Support\FakeEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Ensures migration work stays bounded and reports invalid legacy rows.
 */
#[CoversClass( OccurrenceIndexBatchProcessor::class )]
#[CoversClass( OccurrenceIndexBatchResult::class )]
final class OccurrenceIndexBatchProcessorTest extends TestCase {
	/**
	 * Reset deterministic posts and metadata.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * The processor never inspects more than its fixed batch size.
	 */
	public function test_processing_is_bounded(): void {
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

		$result = $this->processor()->process();

		self::assertSame( OccurrenceIndexBatchProcessor::BATCH_SIZE, $result->processed );
		self::assertSame( OccurrenceIndexBatchProcessor::BATCH_SIZE, $result->skipped_invalid );
		self::assertTrue( $result->has_more );
	}

	/**
	 * Empty incomplete drafts must not be selected repeatedly by the migration.
	 */
	public function test_query_excludes_empty_start_values(): void {
		$this->processor()->process();

		$arguments  = WordPressState::last_get_posts_arguments();
		$meta_query = $arguments['meta_query'] ?? null;

		self::assertIsArray( $meta_query );
		self::assertSame(
			array(
				'key'     => EventMeta::START_LOCAL,
				'value'   => '',
				'compare' => '!=',
			),
			$meta_query[0] ?? null
		);
	}

	/**
	 * Valid canonical data is delegated to the one-off projector.
	 */
	public function test_valid_event_is_indexed(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 42,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
				)
			)
		);
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, '2026-09-04' );
		WordPressState::update_post_meta( 42, EventMeta::END_LOCAL, '2026-09-04' );
		WordPressState::update_post_meta( 42, EventMeta::ALL_DAY, true );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );

		$result = $this->processor()->process();

		self::assertSame( 1, $result->processed );
		self::assertSame( 1, $result->indexed );
		self::assertFalse( $result->has_more );
	}

	/**
	 * Create the processor around an in-memory projection boundary.
	 */
	private function processor(): OccurrenceIndexBatchProcessor {
		return new OccurrenceIndexBatchProcessor(
			new OneOffOccurrenceIndexRepairer(
				new EventValidator(),
				new EventPublicationPolicy(),
				new FakeEventOccurrenceProjector()
			)
		);
	}
}
