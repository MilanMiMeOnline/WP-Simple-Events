<?php
/**
 * Safe recurrence generation exception.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use RuntimeException;

/**
 * Carries a stable non-sensitive reason without exposing raw editor input.
 */
final class RecurrenceGenerationException extends RuntimeException {
	/**
	 * Create an invalid-local-time failure.
	 */
	public static function invalid_local_time(): self {
		return self::from_reason( RecurrenceGenerationFailure::INVALID_LOCAL_TIME );
	}

	/**
	 * Create an output-row-limit failure.
	 */
	public static function row_limit_exceeded(): self {
		return self::from_reason( RecurrenceGenerationFailure::ROW_LIMIT_EXCEEDED );
	}

	/**
	 * Create an internal-evaluation-limit failure.
	 */
	public static function evaluation_limit_reached(): self {
		return self::from_reason( RecurrenceGenerationFailure::EVALUATION_LIMIT_REACHED );
	}

	/**
	 * Create an unsupported-date-range failure.
	 */
	public static function date_outside_supported_range(): self {
		return self::from_reason( RecurrenceGenerationFailure::DATE_OUTSIDE_SUPPORTED_RANGE );
	}

	/**
	 * Create one failure from an allowlisted reason.
	 *
	 * @param RecurrenceGenerationFailure $reason Stable machine-readable reason.
	 */
	private static function from_reason( RecurrenceGenerationFailure $reason ): self {
		$message = match ( $reason ) {
			RecurrenceGenerationFailure::INVALID_LOCAL_TIME => 'A generated occurrence contains an invalid or ambiguous local time.',
			RecurrenceGenerationFailure::ROW_LIMIT_EXCEEDED => 'The recurrence result exceeds its configured row limit.',
			RecurrenceGenerationFailure::EVALUATION_LIMIT_REACHED => 'The recurrence requires too many internal calendar evaluations.',
			RecurrenceGenerationFailure::DATE_OUTSIDE_SUPPORTED_RANGE => 'Recurrence calendar data is outside the supported range.',
		};

		return new self( $message, $reason );
	}

	/**
	 * Store one allowlisted internal failure.
	 *
	 * @param string                      $message Non-sensitive internal description.
	 * @param RecurrenceGenerationFailure $reason  Stable machine-readable reason.
	 */
	private function __construct(
		string $message,
		private readonly RecurrenceGenerationFailure $reason
	) {
		parent::__construct( $message );
	}

	/**
	 * Return the stable machine-readable failure reason.
	 */
	public function reason(): RecurrenceGenerationFailure {
		return $this->reason;
	}
}
