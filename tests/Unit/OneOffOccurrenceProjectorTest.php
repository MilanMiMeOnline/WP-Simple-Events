<?php
/**
 * Tests for one-off occurrence projection.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OneOffOccurrenceProjector;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceSource;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceProjectionStore;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies complete one-row generations and fail-safe repair markers.
 */
#[CoversClass( OneOffOccurrenceProjector::class )]
final class OneOffOccurrenceProjectorTest extends TestCase {
	/**
	 * Reset metadata state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * A complete event becomes exactly one occurrence generation.
	 */
	public function test_projects_one_complete_occurrence(): void {
		update_post_meta( 42, EventMeta::INDEX_DIRTY, true );

		$store     = new FakeOccurrenceProjectionStore();
		$projector = new OneOffOccurrenceProjector( $store );
		$range     = EventDateRange::from_local(
			'2026-09-04T20:00',
			'2026-09-04T22:00',
			false,
			'Europe/Brussels'
		);

		$result = $projector->project_one_off( 42, $range, EventStatus::SCHEDULED );

		self::assertTrue( $result );
		self::assertCount( 1, $store->occurrences );
		self::assertSame( 42, $store->occurrences[0]->event_id );
		self::assertSame( 73, $store->occurrences[0]->generation );
		self::assertSame( OccurrenceSource::ONE_OFF, $store->occurrences[0]->source );
		self::assertNull( $store->coverage );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * An incomplete draft removes only its derived projection.
	 */
	public function test_incomplete_draft_removes_projection(): void {
		$store = new FakeOccurrenceProjectionStore();

		$result = ( new OneOffOccurrenceProjector( $store ) )->project_one_off(
			42,
			null,
			EventStatus::SCHEDULED
		);

		self::assertTrue( $result );
		self::assertSame( array( 42 ), $store->removed_event_ids );
	}

	/**
	 * Projection failure never invalidates canonical event data and marks repair.
	 */
	public function test_failed_projection_sets_repair_marker(): void {
		$store = new FakeOccurrenceProjectionStore( write_result: false );
		$range = EventDateRange::from_local( '2026-09-04', null, true, 'UTC' );

		$result = ( new OneOffOccurrenceProjector( $store ) )->project_one_off(
			42,
			$range,
			EventStatus::SCHEDULED
		);

		self::assertFalse( $result );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}
}
