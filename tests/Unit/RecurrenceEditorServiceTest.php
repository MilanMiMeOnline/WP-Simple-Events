<?php
/**
 * Tests for the authenticated recurrence editor workflow.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\RecurrenceAggregateBootstrapper;
use MiMe\WPSimpleEvents\Application\RecurrenceAggregatePersistence;
use MiMe\WPSimpleEvents\Application\RecurrenceDisableConfirmation;
use MiMe\WPSimpleEvents\Application\RecurrenceDisableCoordinator;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorError;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorException;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorService;
use MiMe\WPSimpleEvents\Application\RecurrencePreviewConfirmation;
use MiMe\WPSimpleEvents\Application\RecurrenceSaveCoordinator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\ManualOccurrence;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEditScope;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingReplacement;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactChange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactPreviewer;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Tests\Support\FakeEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Tests\Support\FakeRecurringEventOccurrenceProjector;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves authorization, bootstrap, preview evidence and stale-safe saves.
 */
#[CoversClass( RecurrenceEditorService::class )]
final class RecurrenceEditorServiceTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/**
	 * Configure one valid editable one-off event.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::allow_current_user( true );
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'         => 42,
					'post_type'  => EventPostType::POST_TYPE,
					'post_title' => 'Series title',
				)
			)
		);
		WordPressState::update_post_meta( 42, EventMeta::SERIES_UID, self::SERIES_UID );
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, '2027-01-04T19:00:00' );
		WordPressState::update_post_meta( 42, EventMeta::END_LOCAL, '2027-01-04T21:00:00' );
		WordPressState::update_post_meta( 42, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( 42, EventMeta::STATUS, 'scheduled' );
		WordPressState::update_post_meta( 42, EventMeta::VENUE, 'Main hall' );
		WordPressState::update_post_meta( 42, EventMeta::ADDRESS, "Main Street 1\nBrussels" );
		WordPressState::update_post_meta( 42, EventMeta::LOCATION_URL, 'https://example.com/location' );
		WordPressState::update_post_meta( 42, EventMeta::EVENT_URL, 'https://example.com/event' );
		WordPressState::update_post_meta( 42, EventMeta::EVENT_URL_LABEL, 'Register' );
		WordPressState::update_post_meta( 42, '_thumbnail_id', 88 );
	}

	/**
	 * One-off state bootstraps and previews a complete recurring proposal.
	 */
	public function test_one_off_context_and_preview_are_complete(): void {
		$store   = new FakeRecurrenceAggregateStore();
		$service = $this->service( $store );
		$context = $service->context( 42 );
		$preview = $service->preview(
			42,
			$this->aggregate(),
			$this->window(),
			RecurrenceEditScope::COMPLETE_SERIES,
			null,
			$context->revision
		);

		self::assertFalse( $context->recurring );
		self::assertSame( self::SERIES_UID, $context->aggregate->series_uid );
		self::assertSame( 6, $preview->impact->count( RecurrenceImpactChange::ADDED ) );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D', $preview->confirmation );
	}

	/**
	 * An exact preview confirmation permits canonical save and projection.
	 */
	public function test_confirmed_preview_saves_atomically(): void {
		$store     = new FakeRecurrenceAggregateStore();
		$projector = new FakeRecurringEventOccurrenceProjector();
		$service   = $this->service( $store, $projector );
		$context   = $service->context( 42 );
		$proposed  = $this->aggregate();
		$preview   = $service->preview( 42, $proposed, $this->window(), RecurrenceEditScope::COMPLETE_SERIES, null, $context->revision );
		$result    = $service->save(
			42,
			$proposed,
			$this->window(),
			RecurrenceEditScope::COMPLETE_SERIES,
			null,
			$context->revision,
			$preview->confirmation
		);

		self::assertTrue( $result->successful() );
		self::assertNotNull( $store->aggregate );
		self::assertSame( 1, $projector->calls );
		self::assertSame( array( 42 ), WordPressState::saved_post_revision_ids() );
	}

	/**
	 * A canonical change remains revisioned when derived projection needs repair.
	 */
	public function test_changed_canonical_state_is_revisioned_when_projection_fails(): void {
		$store     = new FakeRecurrenceAggregateStore();
		$projector = new FakeRecurringEventOccurrenceProjector( false );
		$service   = $this->service( $store, $projector );
		$context   = $service->context( 42 );
		$proposed  = $this->aggregate();
		$preview   = $service->preview( 42, $proposed, $this->window(), RecurrenceEditScope::COMPLETE_SERIES, null, $context->revision );
		$result    = $service->save( 42, $proposed, $this->window(), RecurrenceEditScope::COMPLETE_SERIES, null, $context->revision, $preview->confirmation );

		self::assertFalse( $result->successful() );
		self::assertTrue( $result->changed() );
		self::assertSame( array( 42 ), WordPressState::saved_post_revision_ids() );
	}

	/**
	 * An unchanged clean aggregate does not create revision noise.
	 */
	public function test_unchanged_save_does_not_create_a_revision(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$service          = $this->service( $store );
		$context          = $service->context( 42 );
		$preview          = $service->preview( 42, $store->aggregate, $this->window(), RecurrenceEditScope::COMPLETE_SERIES, null, $context->revision );
		$result           = $service->save( 42, $store->aggregate, $this->window(), RecurrenceEditScope::COMPLETE_SERIES, null, $context->revision, $preview->confirmation );

		self::assertTrue( $result->successful() );
		self::assertFalse( $result->changed() );
		self::assertSame( array(), WordPressState::saved_post_revision_ids() );
	}

	/**
	 * A fabricated confirmation cannot reach persistence or projection.
	 */
	public function test_invalid_confirmation_is_rejected(): void {
		$store     = new FakeRecurrenceAggregateStore();
		$projector = new FakeRecurringEventOccurrenceProjector();
		$service   = $this->service( $store, $projector );
		$context   = $service->context( 42 );

		try {
			$service->save(
				42,
				$this->aggregate(),
				$this->window(),
				RecurrenceEditScope::COMPLETE_SERIES,
				null,
				$context->revision,
				str_repeat( '0', 64 )
			);
			self::fail( 'A fabricated confirmation should fail.' );
		} catch ( RecurrenceEditorException $exception ) {
			self::assertSame( RecurrenceEditorError::INVALID_CONFIRMATION, $exception->error );
		}

		self::assertNull( $store->aggregate );
		self::assertSame( 0, $projector->calls );
		self::assertSame( array(), WordPressState::saved_post_revision_ids() );
	}

	/**
	 * A newer aggregate invalidates a previously issued confirmation.
	 */
	public function test_stale_preview_is_rejected_before_save(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$service          = $this->service( $store );
		$context          = $service->context( 42 );
		$proposed         = $this->aggregate();
		$preview          = $service->preview( 42, $proposed, $this->window(), RecurrenceEditScope::COMPLETE_SERIES, null, $context->revision );
		$store->aggregate = $this->aggregate( 2 );

		try {
			$service->save( 42, $proposed, $this->window(), RecurrenceEditScope::COMPLETE_SERIES, null, $context->revision, $preview->confirmation );
			self::fail( 'A stale preview should fail.' );
		} catch ( RecurrenceEditorException $exception ) {
			self::assertSame( RecurrenceEditorError::STALE_REVISION, $exception->error );
		}
	}

	/**
	 * Event edit permission is required before canonical state is exposed.
	 */
	public function test_unauthorized_context_is_rejected(): void {
		WordPressState::allow_current_user( false );

		try {
			$this->service( new FakeRecurrenceAggregateStore() )->context( 42 );
			self::fail( 'An unauthorized context should fail.' );
		} catch ( RecurrenceEditorException $exception ) {
			self::assertSame( RecurrenceEditorError::FORBIDDEN, $exception->error );
		}
	}

	/**
	 * A recurring series exposes bounded effective survivor choices.
	 */
	public function test_recurring_event_exposes_effective_occurrence_choices(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$occurrences      = $this->service( $store )->occurrences( 42, $this->window() );

		self::assertCount( 7, $occurrences );
		self::assertSame( '2027-01-04T19:00:00', $occurrences[0]->identity->recurrence_id() );
	}

	/**
	 * A moved cancelled occurrence exposes current and original inherited state.
	 */
	public function test_occurrence_context_resolves_moved_target_outside_loaded_original_window(): void {
		$target           = '2027-01-06T19:00:00';
		$moved            = EventDateRange::from_local( '2027-01-20T20:00:00', '2027-01-20T22:00:00', false, 'Europe/Brussels' );
		$override         = OccurrenceOverride::from_fields(
			$target,
			array(
				OccurrenceOverride::DATE_RANGE => $moved,
				OccurrenceOverride::STATUS     => EventStatus::POSTPONED,
				OccurrenceOverride::TITLE      => 'Special edition',
			)
		);
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate(
			1,
			array( $override ),
			array( new OccurrenceExclusion( $target, OccurrenceExclusionAction::CANCEL ) )
		);
		$context          = $this->service( $store )->occurrence_context(
			42,
			$target,
			RecurrenceGenerationWindow::between( '2027-01-20', '2027-01-20', 20 )
		);

		self::assertSame( '2027-01-20T20:00:00', $context->current->date_range->start_local() );
		self::assertSame( EventStatus::CANCELLED, $context->current->status );
		self::assertSame( '2027-01-06T19:00:00', $context->inherited->date_range->start_local() );
		self::assertSame( EventStatus::SCHEDULED, $context->inherited->status );
		self::assertSame( $override, $context->override );
		self::assertSame( OccurrenceExclusionAction::CANCEL, $context->exclusion_action );
		self::assertSame( 'Series title', $context->inherited_fields->title );
		self::assertSame( '', $context->inherited_fields->note );
		self::assertSame( 88, $context->inherited_fields->featured_image_id );
		self::assertSame( 'Main hall', $context->inherited_fields->venue );
		self::assertSame( "Main Street 1\nBrussels", $context->inherited_fields->address );
		self::assertSame( 'https://example.com/location', $context->inherited_fields->location_url );
		self::assertSame( 'https://example.com/event', $context->inherited_fields->event_url );
		self::assertSame( 'Register', $context->inherited_fields->event_url_label );
	}

	/**
	 * A detached generated identity inherits from its stored manual source date.
	 */
	public function test_occurrence_context_resolves_moved_detached_generated_target(): void {
		$target           = '2026-12-01T19:00:00';
		$original         = EventDateRange::from_local( '2027-01-15T19:00:00', '2027-01-15T21:00:00', false, 'Europe/Brussels' );
		$moved            = EventDateRange::from_local( '2027-02-01T20:00:00', '2027-02-01T22:00:00', false, 'Europe/Brussels' );
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate(
			1,
			array( OccurrenceOverride::from_fields( $target, array( OccurrenceOverride::DATE_RANGE => $moved ) ) ),
			array(),
			array( new ManualOccurrence( $target, $original, EventStatus::POSTPONED ) )
		);
		$context          = $this->service( $store )->occurrence_context(
			42,
			$target,
			RecurrenceGenerationWindow::between( '2027-02-01', '2027-02-01', 20 )
		);

		self::assertSame( '2027-02-01T20:00:00', $context->current->date_range->start_local() );
		self::assertSame( '2027-01-15T19:00:00', $context->inherited->date_range->start_local() );
		self::assertSame( EventStatus::POSTPONED, $context->inherited->status );
		self::assertSame( 'manual', $context->inherited->source->value );
	}

	/**
	 * A syntactically valid identity that is not a selected occurrence fails closed.
	 */
	public function test_occurrence_context_rejects_target_missing_from_loaded_window(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();

		try {
			$this->service( $store )->occurrence_context(
				42,
				'2027-01-03T19:00:00',
				RecurrenceGenerationWindow::between( '2027-01-03', '2027-01-03', 20 )
			);
			self::fail( 'An identity absent from the loaded occurrence window should fail.' );
		} catch ( RecurrenceEditorException $exception ) {
			self::assertSame( RecurrenceEditorError::INVALID_PROPOSAL, $exception->error );
		}
	}

	/**
	 * A following preview builds, reconciles and signs the exact server proposal.
	 */
	public function test_following_preview_builds_reconciled_proposal_that_generic_save_accepts(): void {
		$orphaned         = '2027-01-07T19:00:00';
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate(
			1,
			array( OccurrenceOverride::from_fields( $orphaned, array( OccurrenceOverride::TITLE => 'Keep me' ) ) )
		);
		$service          = $this->service( $store );
		$context          = $service->context( 42 );
		$target           = '2027-01-06T19:00:00';
		$preview          = $service->following_preview(
			42,
			$target,
			new RecurrenceFollowingReplacement(
				'2027-01-06T20:00:00',
				'2027-01-06T22:00:00',
				false,
				RecurrenceRule::daily( 2 )
			),
			$this->window(),
			$context->revision
		);

		self::assertSame( RecurrenceEditScope::THIS_AND_FOLLOWING, $preview->impact->scope );
		self::assertSame( $target, $preview->impact->target );
		self::assertCount( 2, $preview->proposal->segments );
		self::assertSame( $target, $preview->proposal->segments[1]->anchor );
		self::assertSame( $orphaned, $preview->proposal->manuals[0]->recurrence_id );

		$result = $service->save(
			42,
			$preview->proposal,
			$this->window(),
			RecurrenceEditScope::THIS_AND_FOLLOWING,
			$target,
			$context->revision,
			$preview->confirmation
		);

		self::assertTrue( $result->successful() );
		self::assertSame( $preview->proposal, $store->aggregate );
	}

	/**
	 * A stale editor cannot obtain signed evidence for a future split.
	 */
	public function test_following_preview_rejects_stale_revision(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();

		try {
			$this->service( $store )->following_preview(
				42,
				'2027-01-06T19:00:00',
				new RecurrenceFollowingReplacement(
					'2027-01-06T20:00:00',
					'2027-01-06T22:00:00',
					false,
					RecurrenceRule::daily( 2 )
				),
				$this->window(),
				str_repeat( '0', 64 )
			);
			self::fail( 'A stale following preview should fail.' );
		} catch ( RecurrenceEditorException $exception ) {
			self::assertSame( RecurrenceEditorError::STALE_REVISION, $exception->error );
		}
	}

	/**
	 * The root occurrence cannot masquerade as a following-only boundary.
	 */
	public function test_following_preview_rejects_root_boundary(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$context          = $this->service( $store )->context( 42 );

		try {
			$this->service( $store )->following_preview(
				42,
				'2027-01-04T19:00:00',
				new RecurrenceFollowingReplacement(
					'2027-01-04T20:00:00',
					'2027-01-04T22:00:00',
					false,
					RecurrenceRule::daily( 2 )
				),
				$this->window(),
				$context->revision
			);
			self::fail( 'The root occurrence should use complete-series editing.' );
		} catch ( RecurrenceEditorException $exception ) {
			self::assertSame( RecurrenceEditorError::INVALID_PROPOSAL, $exception->error );
		}
	}

	/**
	 * Confirmed disable keeps the selected effective occurrence and revisions it.
	 */
	public function test_confirmed_disable_converts_series_to_one_off(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$one_off          = new FakeEventOccurrenceProjector();
		$service          = $this->service( $store, null, $one_off );
		$context          = $service->context( 42 );
		$preview          = $service->disable_preview(
			42,
			'2027-01-06T19:00:00',
			$this->window(),
			$context->revision
		);
		$result           = $service->disable_save(
			42,
			'2027-01-06T19:00:00',
			$this->window(),
			$context->revision,
			$preview->confirmation
		);

		self::assertCount( 6, $preview->removed );
		self::assertTrue( $result->successful() );
		self::assertNull( $store->aggregate );
		self::assertSame( '2027-01-06T19:00:00', WordPressState::post_meta( 42, EventMeta::START_LOCAL ) );
		self::assertNotNull( $one_off->projection );
		self::assertSame( array( 42 ), WordPressState::saved_post_revision_ids() );
	}

	/**
	 * A fabricated disable confirmation cannot remove recurrence.
	 */
	public function test_invalid_disable_confirmation_is_rejected(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$service          = $this->service( $store );
		$context          = $service->context( 42 );

		try {
			$service->disable_save(
				42,
				'2027-01-06T19:00:00',
				$this->window(),
				$context->revision,
				str_repeat( '0', 64 )
			);
			self::fail( 'A fabricated disable confirmation should fail.' );
		} catch ( RecurrenceEditorException $exception ) {
			self::assertSame( RecurrenceEditorError::INVALID_CONFIRMATION, $exception->error );
		}

		self::assertNotNull( $store->aggregate );
		self::assertSame( array(), WordPressState::saved_post_revision_ids() );
	}

	/** Proposed override text must be canonical before impact preview. */
	public function test_noncanonical_override_content_is_rejected_before_preview(): void {
		$store            = new FakeRecurrenceAggregateStore();
		$store->aggregate = $this->aggregate();
		$service          = $this->service( $store );
		$context          = $service->context( 42 );
		$proposal         = $this->aggregate(
			1,
			array(
				OccurrenceOverride::from_fields(
					'2027-01-06T19:00:00',
					array( OccurrenceOverride::TITLE => '<strong>Unsafe</strong>' )
				),
			)
		);

		try {
			$service->preview(
				42,
				$proposal,
				$this->window(),
				RecurrenceEditScope::ONLY_THIS,
				'2027-01-06T19:00:00',
				$context->revision
			);
			self::fail( 'Noncanonical override text should not reach impact preview.' );
		} catch ( RecurrenceEditorException $exception ) {
			self::assertSame( RecurrenceEditorError::INVALID_PROPOSAL, $exception->error );
		}
	}

	/**
	 * Create the workflow around deterministic storage and projection boundaries.
	 *
	 * @param FakeRecurrenceAggregateStore               $store     Canonical fake.
	 * @param FakeRecurringEventOccurrenceProjector|null $projector Optional recurrence projection fake.
	 * @param FakeEventOccurrenceProjector|null          $one_off  Optional one-off projection fake.
	 */
	private function service(
		FakeRecurrenceAggregateStore $store,
		?FakeRecurringEventOccurrenceProjector $projector = null,
		?FakeEventOccurrenceProjector $one_off = null
	): RecurrenceEditorService {
		$projector ??= new FakeRecurringEventOccurrenceProjector();
		$one_off   ??= new FakeEventOccurrenceProjector();

		return new RecurrenceEditorService(
			$store,
			new RecurrenceAggregateBootstrapper(),
			new RecurrenceImpactPreviewer(),
			new RecurrencePreviewConfirmation(),
			new RecurrenceSaveCoordinator( new RecurrenceAggregatePersistence( $store ), $projector ),
			new \MiMe\WPSimpleEvents\Occurrence\RecurrenceOccurrenceBuilder(),
			new RecurrenceDisableConfirmation(),
			new RecurrenceDisableCoordinator( $store, $one_off )
		);
	}

	/**
	 * Return one daily recurrence proposal.
	 *
	 * @param int   $interval   Daily interval.
	 * @param array $overrides  Optional sparse occurrence overrides.
	 * @param array $exclusions Optional occurrence exclusions.
	 * @param array $manuals    Optional manual occurrences.
	 * @phpstan-param list<OccurrenceOverride> $overrides
	 * @phpstan-param list<OccurrenceExclusion> $exclusions
	 * @phpstan-param list<ManualOccurrence> $manuals
	 */
	private function aggregate(
		int $interval = 1,
		array $overrides = array(),
		array $exclusions = array(),
		array $manuals = array()
	): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04T19:00:00', '2027-01-04T21:00:00', false, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $range->start_local(), $range, RecurrenceRule::daily( $interval ) ) ),
			$manuals,
			$exclusions,
			$overrides
		);
	}

	/**
	 * Return the explicit seven-day editor horizon.
	 */
	private function window(): RecurrenceGenerationWindow {
		return RecurrenceGenerationWindow::between( '2027-01-04', '2027-01-10', 20 );
	}
}
