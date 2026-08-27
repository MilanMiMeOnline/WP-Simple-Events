<?php
/**
 * Tests for complete recurrence save coordination.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\RecurrenceAggregatePersistence;
use MiMe\WPSimpleEvents\Application\RecurrencePersistenceError;
use MiMe\WPSimpleEvents\Application\RecurrenceSaveCoordinator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionWindowFactory;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurringEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Post;

/**
 * Proves canonical-first saves, repair retries and fail-closed outcomes.
 */
#[CoversClass( RecurrenceSaveCoordinator::class )]
final class RecurrenceSaveCoordinatorTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/**
	 * Configure one editable canonical event.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::allow_current_user( true );
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);
		WordPressState::update_post_meta( 42, EventMeta::SERIES_UID, self::SERIES_UID );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( 42, EventMeta::STATUS, EventStatus::POSTPONED->value );
	}

	/**
	 * A changed aggregate projects from canonical status after storage succeeds.
	 */
	public function test_changed_aggregate_is_stored_then_projected(): void {
		$projector = new FakeRecurringEventOccurrenceProjector();
		$result    = $this->coordinator( new FakeRecurrenceAggregateStore(), $projector )
			->save( 42, $this->aggregate() );
		$today     = wp_date( 'Y-m-d' );

		self::assertTrue( $result->successful() );
		self::assertTrue( $result->changed() );
		self::assertSame( 1, $projector->calls );
		self::assertSame( EventStatus::POSTPONED, $projector->status );
		self::assertIsString( $today );
		self::assertSame( $today, $projector->window?->from_date() );
		self::assertSame( RecurrenceGenerationWindow::MAX_ROWS, $projector->window?->max_rows() );
	}

	/**
	 * An unchanged healthy aggregate avoids an unnecessary rebuild.
	 */
	public function test_unchanged_clean_aggregate_skips_projection(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$projector        = new FakeRecurringEventOccurrenceProjector();

		$result = $this->coordinator( $store, $projector )->save( 42, $this->aggregate() );

		self::assertTrue( $result->successful() );
		self::assertFalse( $result->changed() );
		self::assertSame( 0, $projector->calls );
	}

	/**
	 * An unchanged dirty aggregate retries projection as an explicit repair path.
	 */
	public function test_unchanged_dirty_aggregate_retries_projection(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$projector        = new FakeRecurringEventOccurrenceProjector();
		WordPressState::update_post_meta( 42, EventMeta::INDEX_DIRTY, true );

		$result = $this->coordinator( $store, $projector )->save( 42, $this->aggregate() );

		self::assertTrue( $result->successful() );
		self::assertFalse( $result->changed() );
		self::assertSame( 1, $projector->calls );
	}

	/**
	 * Projection failure reports canonical change and leaves the dirty guard intact.
	 */
	public function test_projection_failure_is_specific_and_fail_closed(): void {
		$result = $this->coordinator(
			new FakeRecurrenceAggregateStore(),
			new FakeRecurringEventOccurrenceProjector( false )
		)->save( 42, $this->aggregate() );

		self::assertFalse( $result->successful() );
		self::assertTrue( $result->changed() );
		self::assertSame( RecurrencePersistenceError::PROJECTION_FAILED, $result->error() );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * An unavailable production window fails closed before derived storage.
	 */
	public function test_projection_window_failure_is_specific_and_fail_closed(): void {
		$projector = new FakeRecurringEventOccurrenceProjector();
		$windows   = new class() implements OccurrenceProjectionWindowFactory {
			/**
			 * Refuse to produce a projection window.
			 *
			 * @throws RuntimeException Always, to model an unavailable local date.
			 */
			public function current(): RecurrenceGenerationWindow {
				throw new RuntimeException( 'Unavailable local date.' );
			}
		};
		$result    = $this->coordinator(
			new FakeRecurrenceAggregateStore(),
			$projector,
			$windows
		)->save( 42, $this->aggregate() );

		self::assertFalse( $result->successful() );
		self::assertTrue( $result->changed() );
		self::assertSame( RecurrencePersistenceError::PROJECTION_FAILED, $result->error() );
		self::assertSame( 0, $projector->calls );
		self::assertTrue( WordPressState::post_meta( 42, EventMeta::INDEX_DIRTY ) );
	}

	/**
	 * Canonical persistence rejection never reaches derived storage.
	 */
	public function test_persistence_failure_never_projects(): void {
		$projector = new FakeRecurringEventOccurrenceProjector();
		$result    = $this->coordinator( new FakeRecurrenceAggregateStore( false ), $projector )
			->save( 42, $this->aggregate() );

		self::assertSame( RecurrencePersistenceError::STORAGE_FAILED, $result->error() );
		self::assertSame( 0, $projector->calls );
	}

	/**
	 * A stale editor revision never reaches projection.
	 */
	public function test_stale_revision_never_projects(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$projector        = new FakeRecurringEventOccurrenceProjector();
		$result           = $this->coordinator( $store, $projector )->save(
			42,
			$this->aggregate(),
			str_repeat( '0', 64 )
		);

		self::assertSame( RecurrencePersistenceError::STALE_REVISION, $result->error() );
		self::assertSame( 0, $projector->calls );
	}

	/**
	 * Create the coordinator around deterministic boundaries.
	 *
	 * @param FakeRecurrenceAggregateStore           $store     Canonical fake.
	 * @param FakeRecurringEventOccurrenceProjector  $projector Projection fake.
	 * @param OccurrenceProjectionWindowFactory|null $windows   Optional projection window source.
	 */
	private function coordinator(
		FakeRecurrenceAggregateStore $store,
		FakeRecurringEventOccurrenceProjector $projector,
		?OccurrenceProjectionWindowFactory $windows = null
	): RecurrenceSaveCoordinator {
		return null === $windows
			? new RecurrenceSaveCoordinator( new RecurrenceAggregatePersistence( $store ), $projector )
			: new RecurrenceSaveCoordinator(
				new RecurrenceAggregatePersistence( $store ),
				$projector,
				windows: $windows
			);
	}

	/**
	 * Return one minimal aggregate.
	 */
	private function aggregate(): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04', null, true, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, '2027-01-04', $range, RecurrenceRule::daily() ) )
		);
	}
}
