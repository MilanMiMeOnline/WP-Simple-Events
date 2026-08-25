<?php
/**
 * This-and-following replacement input.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Carries one exact client-requested replacement without accepting a timezone.
 */
final readonly class RecurrenceFollowingReplacement {
	/**
	 * Store one strictly shaped replacement request.
	 *
	 * @param string               $start_local Canonical local start candidate.
	 * @param string               $end_local   Canonical local end candidate.
	 * @param bool                 $all_day     Whether the replacement is all day.
	 * @param RecurrenceDefinition $definition Validated replacement definition.
	 */
	public function __construct(
		public string $start_local,
		public string $end_local,
		public bool $all_day,
		public RecurrenceDefinition $definition
	) {}
}
