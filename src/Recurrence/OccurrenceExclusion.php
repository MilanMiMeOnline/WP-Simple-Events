<?php
/**
 * Generated occurrence exclusion.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;

/**
 * Applies one reversible skip or cancellation to an original generated slot.
 */
final readonly class OccurrenceExclusion {
	/**
	 * Create one validated generated-slot exception.
	 *
	 * @param string                    $recurrence_id Original generated recurrence identity.
	 * @param OccurrenceExclusionAction $action        Skip or cancellation semantics.
	 * @throws InvalidArgumentException When the identity is not a generated slot.
	 */
	public function __construct(
		public string $recurrence_id,
		public OccurrenceExclusionAction $action
	) {
		if ( ! OccurrenceIdentity::is_generated_recurrence_id( $this->recurrence_id ) ) {
			throw new InvalidArgumentException( 'An occurrence exclusion requires a generated recurrence identity.' );
		}
	}
}
