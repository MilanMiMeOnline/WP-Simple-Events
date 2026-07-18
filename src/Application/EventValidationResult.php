<?php
/**
 * Event validation result.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Represents either safe event data or one or more stable error codes.
 */
final readonly class EventValidationResult {
	/**
	 * Store one validation outcome.
	 *
	 * @param ValidatedEventData|null $data   Validated values.
	 * @param EventValidationError[]  $errors Validation errors.
	 */
	private function __construct(
		private ?ValidatedEventData $data,
		private array $errors
	) {}

	/**
	 * Create a successful result.
	 *
	 * @param ValidatedEventData $data Validated event data.
	 */
	public static function valid( ValidatedEventData $data ): self {
		return new self( $data, array() );
	}

	/**
	 * Create a failed result.
	 *
	 * @param EventValidationError[] $errors Validation errors.
	 */
	public static function invalid( array $errors ): self {
		$unique = array();

		foreach ( $errors as $error ) {
			$unique[ $error->value ] = $error;
		}

		return new self( null, array_values( $unique ) );
	}

	/**
	 * Whether all event fields passed validation.
	 */
	public function is_valid(): bool {
		return null !== $this->data;
	}

	/**
	 * Return validated data when successful.
	 */
	public function data(): ?ValidatedEventData {
		return $this->data;
	}

	/**
	 * Return stable validation error codes.
	 *
	 * @return EventValidationError[]
	 */
	public function errors(): array {
		return $this->errors;
	}
}
