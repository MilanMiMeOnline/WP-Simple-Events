<?php
/**
 * Authenticated recurrence editor application workflow.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\RecurrenceOccurrenceBuilder;
use MiMe\WPSimpleEvents\Recurrence\ConcurrentRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEditScope;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingExceptionReconciler;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingMutator;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingReplacement;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactPreviewer;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use RuntimeException;

/**
 * Keeps authorization, preview confirmation and concurrency out of REST adapters.
 */
final readonly class RecurrenceEditorService {
	/**
	 * Create the editor workflow.
	 *
	 * @param ConcurrentRecurrenceAggregateStore      $store         Versioned canonical store.
	 * @param RecurrenceAggregateBootstrapper         $bootstrapper  Valid one-off comparison state.
	 * @param RecurrenceImpactPreviewer               $previewer     Scope-safe bounded impact service.
	 * @param RecurrencePreviewConfirmation           $confirmations Server-signed preview evidence.
	 * @param RecurrenceSaveCoordinator               $saves         Canonical-first projection save.
	 * @param RecurrenceOccurrenceBuilder             $builder       Effective occurrence reconciler.
	 * @param RecurrenceDisableConfirmation           $disable_confirmations Destructive preview evidence.
	 * @param RecurrenceDisableCoordinator            $disable_saves Recurrence-to-one-off conversion.
	 * @param RecurrenceAggregateContentGuard         $content_guard WordPress override input boundary.
	 * @param RecurrenceFollowingMutator              $following_mutator Structural future-schedule mutation.
	 * @param RecurrenceFollowingExceptionReconciler  $following_reconciler Lossless future exceptions.
	 * @param RecurrenceOccurrenceInheritanceResolver $inheritance Series-owned occurrence fields.
	 */
	public function __construct(
		private ConcurrentRecurrenceAggregateStore $store = new WordPressRecurrenceAggregateStore(),
		private RecurrenceAggregateBootstrapper $bootstrapper = new RecurrenceAggregateBootstrapper(),
		private RecurrenceImpactPreviewer $previewer = new RecurrenceImpactPreviewer(),
		private RecurrencePreviewConfirmation $confirmations = new RecurrencePreviewConfirmation(),
		private RecurrenceSaveCoordinator $saves = new RecurrenceSaveCoordinator(),
		private RecurrenceOccurrenceBuilder $builder = new RecurrenceOccurrenceBuilder(),
		private RecurrenceDisableConfirmation $disable_confirmations = new RecurrenceDisableConfirmation(),
		private RecurrenceDisableCoordinator $disable_saves = new RecurrenceDisableCoordinator(),
		private RecurrenceAggregateContentGuard $content_guard = new RecurrenceAggregateContentGuard(),
		private RecurrenceFollowingMutator $following_mutator = new RecurrenceFollowingMutator(),
		private RecurrenceFollowingExceptionReconciler $following_reconciler = new RecurrenceFollowingExceptionReconciler(),
		private RecurrenceOccurrenceInheritanceResolver $inheritance = new RecurrenceOccurrenceInheritanceResolver()
	) {}

	/**
	 * Build and preview one server-owned this-and-following proposal.
	 *
	 * @param int                            $event_id          Canonical event post ID.
	 * @param string                         $target            Selected generated boundary.
	 * @param RecurrenceFollowingReplacement $replacement       Strict replacement input.
	 * @param RecurrenceGenerationWindow     $window            Exact bounded preview window.
	 * @param string                         $expected_revision Revision loaded by the editor.
	 * @throws RecurrenceEditorException When state, boundary or proposal is invalid.
	 */
	public function following_preview(
		int $event_id,
		string $target,
		RecurrenceFollowingReplacement $replacement,
		RecurrenceGenerationWindow $window,
		string $expected_revision
	): RecurrenceFollowingEditorPreview {
		$edit_context = $this->occurrence_context( $event_id, $target, $window );
		$context      = $edit_context->context;

		if ( ! hash_equals( $context->revision, $expected_revision ) ) {
			$this->fail( RecurrenceEditorError::STALE_REVISION );
		}

		try {
			$template = EventDateRange::from_local(
				$replacement->start_local,
				$replacement->end_local,
				$replacement->all_day,
				$context->aggregate->timezone
			);
			$proposal = $this->following_mutator->replace_from(
				$context->aggregate,
				$target,
				$template,
				$replacement->definition
			);
			$proposal = $this->following_reconciler->reconcile(
				$context->aggregate,
				$proposal,
				$target,
				$context->status
			);
			$this->content_guard->assert_canonical( $proposal );
			$impact       = $this->previewer->preview(
				$context->aggregate,
				$proposal,
				$context->status,
				$window,
				RecurrenceEditScope::THIS_AND_FOLLOWING,
				$target
			);
			$confirmation = $this->confirmations->issue(
				$event_id,
				get_current_user_id(),
				$context->revision,
				$proposal,
				RecurrenceEditScope::THIS_AND_FOLLOWING,
				$target,
				$window,
				wp_salt( 'nonce' )
			);
		} catch ( InvalidArgumentException | RuntimeException ) {
			$this->fail( RecurrenceEditorError::INVALID_PROPOSAL );
		}

		return new RecurrenceFollowingEditorPreview( $context, $proposal, $impact, $confirmation );
	}

	/**
	 * Load one authorized canonical or bootstrapped editor context.
	 *
	 * @param int $event_id Canonical event post ID.
	 * @throws RecurrenceEditorException When authorization or stored state is invalid.
	 */
	public function context( int $event_id ): RecurrenceEditorContext {
		if ( $event_id <= 0 || EventPostType::POST_TYPE !== get_post_type( $event_id ) ) {
			$this->fail( RecurrenceEditorError::INVALID_EVENT );
		}

		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			$this->fail( RecurrenceEditorError::FORBIDDEN );
		}

		try {
			$snapshot  = $this->store->snapshot( $event_id );
			$aggregate = $snapshot->aggregate ?? $this->bootstrapper->from_event( $event_id );
			$this->content_guard->assert_canonical( $aggregate );
		} catch ( InvalidArgumentException | RuntimeException ) {
			$this->fail( RecurrenceEditorError::INVALID_STATE );
		}

		$status = get_post_meta( $event_id, EventMeta::STATUS, true );
		$status = is_string( $status ) ? EventStatus::tryFrom( $status ) : null;

		if ( null === $status ) {
			$this->fail( RecurrenceEditorError::INVALID_STATE );
		}

		return new RecurrenceEditorContext(
			$aggregate,
			null !== $snapshot->aggregate,
			$snapshot->revision,
			$status
		);
	}

	/**
	 * Preview one proposed complete aggregate and issue save confirmation.
	 *
	 * @param int                        $event_id         Canonical event post ID.
	 * @param RecurrenceAggregate        $proposed         Complete proposed aggregate.
	 * @param RecurrenceGenerationWindow $window           Explicit bounded preview window.
	 * @param RecurrenceEditScope        $scope            Scope selected before editing.
	 * @param string|null                $target           Optional selected occurrence identity.
	 * @param string                     $expected_revision Revision loaded by the editor.
	 * @throws RecurrenceEditorException When state, revision, proposal or authorization is invalid.
	 */
	public function preview(
		int $event_id,
		RecurrenceAggregate $proposed,
		RecurrenceGenerationWindow $window,
		RecurrenceEditScope $scope,
		?string $target,
		string $expected_revision
	): RecurrenceEditorPreview {
		$context = $this->context( $event_id );

		if ( ! hash_equals( $context->revision, $expected_revision ) ) {
			$this->fail( RecurrenceEditorError::STALE_REVISION );
		}

		try {
			$this->content_guard->assert_canonical( $proposed );
			$impact       = $this->previewer->preview(
				$context->aggregate,
				$proposed,
				$context->status,
				$window,
				$scope,
				$target
			);
			$confirmation = $this->confirmations->issue(
				$event_id,
				get_current_user_id(),
				$context->revision,
				$proposed,
				$scope,
				$target,
				$window,
				wp_salt( 'nonce' )
			);
		} catch ( InvalidArgumentException | RuntimeException ) {
			$this->fail( RecurrenceEditorError::INVALID_PROPOSAL );
		}

		return new RecurrenceEditorPreview( $context, $impact, $confirmation );
	}

	/**
	 * Revalidate, confirm and atomically save one previewed aggregate.
	 *
	 * @param int                        $event_id         Canonical event post ID.
	 * @param RecurrenceAggregate        $proposed         Complete proposed aggregate.
	 * @param RecurrenceGenerationWindow $window           Exact preview window.
	 * @param RecurrenceEditScope        $scope            Exact preview scope.
	 * @param string|null                $target           Exact preview target.
	 * @param string                     $expected_revision Exact preview source revision.
	 * @param mixed                      $confirmation     Untrusted preview confirmation.
	 * @throws RecurrenceEditorException When preview evidence or state is invalid.
	 */
	public function save(
		int $event_id,
		RecurrenceAggregate $proposed,
		RecurrenceGenerationWindow $window,
		RecurrenceEditScope $scope,
		?string $target,
		string $expected_revision,
		mixed $confirmation
	): RecurrencePersistenceResult {
		$this->preview( $event_id, $proposed, $window, $scope, $target, $expected_revision );

		if ( ! $this->confirmations->valid(
			$confirmation,
			$event_id,
			get_current_user_id(),
			$expected_revision,
			$proposed,
			$scope,
			$target,
			$window,
			wp_salt( 'nonce' )
		) ) {
			$this->fail( RecurrenceEditorError::INVALID_CONFIRMATION );
		}

		$result = $this->saves->save( $event_id, $proposed, $window, $expected_revision );

		if ( $result->changed() ) {
			wp_save_post_revision( $event_id );
		}

		return $result;
	}

	/**
	 * Return bounded effective survivor choices for one recurring event.
	 *
	 * @param int                        $event_id Canonical event post ID.
	 * @param RecurrenceGenerationWindow $window Explicit bounded occurrence window.
	 * @return list<EventOccurrence>
	 * @throws RecurrenceEditorException When state or generation is invalid.
	 */
	public function occurrences( int $event_id, RecurrenceGenerationWindow $window ): array {
		$context = $this->context( $event_id );

		if ( ! $context->recurring ) {
			$this->fail( RecurrenceEditorError::INVALID_STATE );
		}

		try {
			return $this->builder->build( $event_id, $context->aggregate, $context->status, $window, 1 );
		} catch ( InvalidArgumentException ) {
			$this->fail( RecurrenceEditorError::INVALID_STATE );
		}
	}

	/**
	 * Resolve effective, inherited and sparse exception state for one occurrence.
	 *
	 * The selected occurrence must exist inside the exact supplied window. A moved
	 * target may inherit from an original slot outside that window, so its baseline
	 * is resolved again through one bounded identity-local window when necessary.
	 *
	 * @param int                        $event_id Canonical event post ID.
	 * @param string                     $target Immutable generated or manual identity.
	 * @param RecurrenceGenerationWindow $window Exact bounded occurrence-selection window.
	 * @throws RecurrenceEditorException When state, target or generation is invalid.
	 */
	public function occurrence_context(
		int $event_id,
		string $target,
		RecurrenceGenerationWindow $window
	): RecurrenceOccurrenceEditContext {
		$context = $this->context( $event_id );

		if ( ! $context->recurring
			|| 'one-off' === $target
			|| ! OccurrenceIdentity::valid_recurrence_id( $target )
		) {
			$this->fail( RecurrenceEditorError::INVALID_STATE );
		}

		try {
			$current = $this->find_occurrence(
				$this->builder->build( $event_id, $context->aggregate, $context->status, $window, 1 ),
				$target
			);

			if ( null === $current ) {
				$this->fail( RecurrenceEditorError::INVALID_PROPOSAL );
			}

			$override         = $this->find_override( $context->aggregate, $target );
			$exclusion_action = $this->find_exclusion_action( $context->aggregate, $target );
			$baseline         = $this->without_target_exceptions( $context->aggregate, $target );
			$inherited        = $this->find_occurrence(
				$this->builder->build( $event_id, $baseline, $context->status, $window, 1 ),
				$target
			);

			if ( null === $inherited ) {
				$fallback_date = $this->inherited_target_date( $baseline, $target );
				$inherited     = $this->find_occurrence(
					$this->builder->build(
						$event_id,
						$baseline,
						$context->status,
						RecurrenceGenerationWindow::between(
							$fallback_date,
							$fallback_date,
							RecurrenceGenerationWindow::MAX_ROWS
						),
						1
					),
					$target
				);
			}

			if ( null === $inherited ) {
				$this->fail( RecurrenceEditorError::INVALID_STATE );
			}

			$inherited_fields = $this->inheritance->resolve( $event_id );
		} catch ( RecurrenceEditorException $exception ) {
			throw $exception;
		} catch ( InvalidArgumentException | RuntimeException ) {
			$this->fail( RecurrenceEditorError::INVALID_STATE );
		}

		return new RecurrenceOccurrenceEditContext(
			$context,
			$window,
			$current,
			$inherited,
			$inherited_fields,
			$override,
			$exclusion_action
		);
	}

	/**
	 * Preview retaining one occurrence and removing recurrence completely.
	 *
	 * @param int                        $event_id Canonical event post ID.
	 * @param string                     $target Selected surviving recurrence identity.
	 * @param RecurrenceGenerationWindow $window Exact bounded preview window.
	 * @param string                     $expected_revision Revision loaded by the editor.
	 * @throws RecurrenceEditorException When state, target or revision is invalid.
	 */
	public function disable_preview(
		int $event_id,
		string $target,
		RecurrenceGenerationWindow $window,
		string $expected_revision
	): RecurrenceDisablePreview {
		$context = $this->context( $event_id );

		if ( ! $context->recurring ) {
			$this->fail( RecurrenceEditorError::INVALID_STATE );
		}

		if ( ! hash_equals( $context->revision, $expected_revision ) ) {
			$this->fail( RecurrenceEditorError::STALE_REVISION );
		}

		try {
			$occurrences = $this->builder->build( $event_id, $context->aggregate, $context->status, $window, 1 );
			$survivor    = $this->find_occurrence( $occurrences, $target );

			if ( null === $survivor ) {
				$this->fail( RecurrenceEditorError::INVALID_PROPOSAL );
			}

			$removed      = array_values(
				array_filter(
					$occurrences,
					static fn ( EventOccurrence $occurrence ): bool => $occurrence->identity->recurrence_id() !== $target
				)
			);
			$confirmation = $this->disable_confirmations->issue(
				$event_id,
				get_current_user_id(),
				$context->revision,
				$target,
				$window,
				wp_salt( 'nonce' )
			);
		} catch ( RecurrenceEditorException $exception ) {
			throw $exception;
		} catch ( InvalidArgumentException | RuntimeException ) {
			$this->fail( RecurrenceEditorError::INVALID_PROPOSAL );
		}

		return new RecurrenceDisablePreview(
			$context,
			$survivor,
			$removed,
			count( $context->aggregate->manuals )
				+ count( $context->aggregate->exclusions )
				+ count( $context->aggregate->overrides ),
			$confirmation
		);
	}

	/**
	 * Revalidate and convert one exactly confirmed series into a one-off event.
	 *
	 * @param int                        $event_id Canonical event post ID.
	 * @param string                     $target Selected surviving recurrence identity.
	 * @param RecurrenceGenerationWindow $window Exact bounded preview window.
	 * @param string                     $expected_revision Exact preview source revision.
	 * @param mixed                      $confirmation Untrusted preview confirmation.
	 * @throws RecurrenceEditorException When preview evidence or state is invalid.
	 */
	public function disable_save(
		int $event_id,
		string $target,
		RecurrenceGenerationWindow $window,
		string $expected_revision,
		mixed $confirmation
	): RecurrencePersistenceResult {
		$preview = $this->disable_preview( $event_id, $target, $window, $expected_revision );

		if ( ! $this->disable_confirmations->valid(
			$confirmation,
			$event_id,
			get_current_user_id(),
			$expected_revision,
			$target,
			$window,
			wp_salt( 'nonce' )
		) ) {
			$this->fail( RecurrenceEditorError::INVALID_CONFIRMATION );
		}

		$result = $this->disable_saves->disable(
			$event_id,
			$preview->survivor,
			$expected_revision
		);

		if ( $result->changed() ) {
			wp_save_post_revision( $event_id );
		}

		return $result;
	}

	/**
	 * Find one effective occurrence by its immutable recurrence identity.
	 *
	 * @param array  $occurrences Bounded effective occurrences.
	 * @param string $target Selected recurrence identity.
	 * @phpstan-param list<EventOccurrence> $occurrences
	 */
	private function find_occurrence( array $occurrences, string $target ): ?EventOccurrence {
		foreach ( $occurrences as $occurrence ) {
			if ( $occurrence->identity->recurrence_id() === $target ) {
				return $occurrence;
			}
		}

		return null;
	}

	/**
	 * Return one sparse target override without changing canonical ordering.
	 *
	 * @param RecurrenceAggregate $aggregate Current canonical aggregate.
	 * @param string              $target    Selected recurrence identity.
	 */
	private function find_override( RecurrenceAggregate $aggregate, string $target ): ?OccurrenceOverride {
		foreach ( $aggregate->overrides as $override ) {
			if ( $override->recurrence_id === $target ) {
				return $override;
			}
		}

		return null;
	}

	/**
	 * Return one target exclusion action.
	 *
	 * @param RecurrenceAggregate $aggregate Current canonical aggregate.
	 * @param string              $target    Selected recurrence identity.
	 */
	private function find_exclusion_action(
		RecurrenceAggregate $aggregate,
		string $target
	): ?OccurrenceExclusionAction {
		foreach ( $aggregate->exclusions as $exclusion ) {
			if ( $exclusion->recurrence_id === $target ) {
				return $exclusion->action;
			}
		}

		return null;
	}

	/**
	 * Build the inherited aggregate by removing exceptions only for the target.
	 *
	 * @param RecurrenceAggregate $aggregate Current canonical aggregate.
	 * @param string              $target    Selected recurrence identity.
	 */
	private function without_target_exceptions(
		RecurrenceAggregate $aggregate,
		string $target
	): RecurrenceAggregate {
		return RecurrenceAggregate::create(
			$aggregate->series_uid,
			$aggregate->timezone,
			$aggregate->segments,
			$aggregate->manuals,
			array_values(
				array_filter(
					$aggregate->exclusions,
					static fn ( OccurrenceExclusion $exclusion ): bool => $exclusion->recurrence_id !== $target
				)
			),
			array_values(
				array_filter(
					$aggregate->overrides,
					static fn ( OccurrenceOverride $override ): bool => $override->recurrence_id !== $target
				)
			)
		);
	}

	/**
	 * Resolve the target's original local calendar date for one bounded fallback.
	 *
	 * Detached generated identities live in the manual collection, so manual
	 * membership takes precedence over interpreting the identity as a rule slot.
	 *
	 * @param RecurrenceAggregate $aggregate Target-exception-free aggregate.
	 * @param string              $target    Selected recurrence identity.
	 */
	private function inherited_target_date( RecurrenceAggregate $aggregate, string $target ): string {
		foreach ( $aggregate->manuals as $manual ) {
			if ( $manual->recurrence_id === $target ) {
				return substr( $manual->date_range->start_local(), 0, 10 );
			}
		}

		if ( OccurrenceIdentity::is_generated_recurrence_id( $target ) ) {
			return substr( $target, 0, 10 );
		}

		$this->fail( RecurrenceEditorError::INVALID_STATE );
	}

	/**
	 * Throw one allowlisted internal editor failure.
	 *
	 * @param RecurrenceEditorError $error Stable non-sensitive failure code.
	 * @return never
	 * @throws RecurrenceEditorException Always, with the supplied allowlisted code.
	 */
	private function fail( RecurrenceEditorError $error ): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal enum, not browser output.
		throw new RecurrenceEditorException( $error );
	}
}
