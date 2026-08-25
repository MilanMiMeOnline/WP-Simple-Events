<?php
/**
 * Authorized recurrence editor context.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;

/**
 * Carries canonical or bootstrapped state used by an editor preview.
 */
final readonly class RecurrenceEditorContext {
	/**
	 * Store one authorized context.
	 *
	 * @param RecurrenceAggregate $aggregate Recurring or bootstrapped one-off comparison state.
	 * @param bool                $recurring Whether canonical recurrence already exists.
	 * @param string              $revision  Exact canonical storage revision.
	 * @param EventStatus         $status    Canonical inherited event status.
	 */
	public function __construct(
		public RecurrenceAggregate $aggregate,
		public bool $recurring,
		public string $revision,
		public EventStatus $status
	) {}
}
