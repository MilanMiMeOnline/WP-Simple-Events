<?php
/**
 * Authorized recurrence editor preview result.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactPreview;

/**
 * Binds one human-readable impact to a server confirmation token.
 */
final readonly class RecurrenceEditorPreview {
	/**
	 * Store one complete preview result.
	 *
	 * @param RecurrenceEditorContext $context      Current authorized state.
	 * @param RecurrenceImpactPreview $impact       Bounded proposed impact.
	 * @param string                  $confirmation Server-signed confirmation token.
	 */
	public function __construct(
		public RecurrenceEditorContext $context,
		public RecurrenceImpactPreview $impact,
		public string $confirmation
	) {}
}
