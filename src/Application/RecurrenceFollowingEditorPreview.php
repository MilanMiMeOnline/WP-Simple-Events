<?php
/**
 * Server-built this-and-following preview.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactPreview;

/**
 * Returns the exact reconciled proposal that one signed preview confirmed.
 */
final readonly class RecurrenceFollowingEditorPreview {
	/**
	 * Store one complete following-scope preview.
	 *
	 * @param RecurrenceEditorContext $context      Current authorized context.
	 * @param RecurrenceAggregate     $proposal     Exact server-built complete proposal.
	 * @param RecurrenceImpactPreview $impact       Bounded scope-safe impact.
	 * @param string                  $confirmation Server-signed preview evidence.
	 */
	public function __construct(
		public RecurrenceEditorContext $context,
		public RecurrenceAggregate $proposal,
		public RecurrenceImpactPreview $impact,
		public string $confirmation
	) {}
}
