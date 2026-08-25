<?php
/**
 * Authorized occurrence-scoped recurrence editor context.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Carries effective and inherited state for one immutable occurrence identity.
 */
final readonly class RecurrenceOccurrenceEditContext {
	/**
	 * Store one authorized occurrence-scoped editor context.
	 *
	 * @param RecurrenceEditorContext             $context          Current canonical aggregate context.
	 * @param RecurrenceGenerationWindow          $window           Exact bounded window used to select the target.
	 * @param EventOccurrence                     $current          Current effective occurrence.
	 * @param EventOccurrence                     $inherited        Occurrence with only target exceptions removed.
	 * @param RecurrenceOccurrenceInheritedFields $inherited_fields Normalized series-owned field values.
	 * @param OccurrenceOverride|null             $override         Existing sparse target override.
	 * @param OccurrenceExclusionAction|null      $exclusion_action Existing target cancellation, when present.
	 */
	public function __construct(
		public RecurrenceEditorContext $context,
		public RecurrenceGenerationWindow $window,
		public EventOccurrence $current,
		public EventOccurrence $inherited,
		public RecurrenceOccurrenceInheritedFields $inherited_fields,
		public ?OccurrenceOverride $override,
		public ?OccurrenceExclusionAction $exclusion_action
	) {}
}
