<?php
/**
 * Authorized recurrence-disable preview result.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;

/**
 * Binds the survivor and bounded removals to server confirmation evidence.
 */
final readonly class RecurrenceDisablePreview {
	/**
	 * Store one complete destructive preview.
	 *
	 * @param RecurrenceEditorContext $context Current authorized recurring state.
	 * @param EventOccurrence         $survivor Exact effective occurrence retained.
	 * @param array                   $removed Bounded effective occurrences removed.
	 * @param int                     $exception_affected Canonical exception records discarded.
	 * @param string                  $confirmation Server-signed confirmation.
	 * @phpstan-param list<EventOccurrence> $removed
	 */
	public function __construct(
		public RecurrenceEditorContext $context,
		public EventOccurrence $survivor,
		public array $removed,
		public int $exception_affected,
		public string $confirmation
	) {}
}
