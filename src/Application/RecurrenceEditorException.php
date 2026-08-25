<?php
/**
 * Recurrence editor application exception.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use RuntimeException;

/**
 * Carries one allowlisted editor failure without exposing internal details.
 */
final class RecurrenceEditorException extends RuntimeException {
	/**
	 * Create one stable editor failure.
	 *
	 * @param RecurrenceEditorError $error Allowlisted failure code.
	 */
	public function __construct( public readonly RecurrenceEditorError $error ) {
		parent::__construct( $error->value );
	}
}
