<?php
/**
 * Tests for existing one-off occurrence repair.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\EventPublicationPolicy;
use MiMe\WPSimpleEvents\Application\EventValidator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexRepairStatus;
use MiMe\WPSimpleEvents\Occurrence\OneOffOccurrenceIndexRepairer;
use MiMe\WPSimpleEvents\Tests\Support\FakeEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies canonical validation, idempotence and repair markers.
 */
#[CoversClass( OneOffOccurrenceIndexRepairer::class )]
final class OneOffOccurrenceIndexRepairerTest extends TestCase {
	/**
	 * Reset deterministic metadata.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Valid canonical legacy data is projected without reading derived UTC metadata.
	 */
	public function test_projects_valid_canonical_event(): void {
		$this->store_valid_range( 42 );
		$projector = new FakeEventOccurrenceProjector();
		$repairer  = $this->repairer( $projector );

		$result = $repairer->repair( 42, 'publish' );

		self::assertSame( OccurrenceIndexRepairStatus::INDEXED, $result );
		self::assertNotNull( $projector->projection );
		self::assertSame( EventStatus::POSTPONED, $projector->projection['status'] );
	}

	/**
	 * A healthy active generation is not rebuilt during migration.
	 */
	public function test_existing_healthy_generation_is_unchanged(): void {
		WordPressState::update_post_meta( 42, EventMeta::ACTIVE_GENERATION, 73 );
		$projector = new FakeEventOccurrenceProjector();

		$result = $this->repairer( $projector )->repair( 42, 'publish' );

		self::assertSame( OccurrenceIndexRepairStatus::UNCHANGED, $result );
		self::assertNull( $projector->projection );
	}

	/**
	 * Invalid canonical legacy data is isolated and marked for review.
	 */
	public function test_invalid_published_event_is_marked_dirty(): void {
		$result = $this->repairer( new FakeEventOccurrenceProjector() )->repair( 42, 'publish' );

		self::assertSame( OccurrenceIndexRepairStatus::INVALID, $result );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * A failed writer cannot make an event look successfully indexed.
	 */
	public function test_projection_failure_is_marked_dirty(): void {
		$this->store_valid_range( 42 );

		$result = $this->repairer( new FakeEventOccurrenceProjector( false ) )->repair( 42, 'publish' );

		self::assertSame( OccurrenceIndexRepairStatus::FAILED, $result );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * Create the repairer with a deterministic projector.
	 *
	 * @param FakeEventOccurrenceProjector $projector In-memory projection boundary.
	 */
	private function repairer( FakeEventOccurrenceProjector $projector ): OneOffOccurrenceIndexRepairer {
		return new OneOffOccurrenceIndexRepairer(
			new EventValidator(),
			new EventPublicationPolicy(),
			$projector
		);
	}

	/**
	 * Store one complete canonical event record.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function store_valid_range( int $event_id ): void {
		WordPressState::update_post_meta( $event_id, EventMeta::START_LOCAL, '2026-09-04T20:00:00' );
		WordPressState::update_post_meta( $event_id, EventMeta::END_LOCAL, '2026-09-04T22:00:00' );
		WordPressState::update_post_meta( $event_id, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( $event_id, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( $event_id, EventMeta::STATUS, EventStatus::POSTPONED->value );
	}
}
