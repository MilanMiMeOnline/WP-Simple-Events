<?php
/**
 * Recurrence persistence result.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Represents a stored, unchanged or rejected complete recurrence aggregate.
 */
final readonly class RecurrencePersistenceResult {
	/**
	 * Store one persistence outcome.
	 *
	 * @param bool                            $successful Whether canonical storage is safe.
	 * @param bool                            $changed    Whether the aggregate changed.
	 * @param RecurrencePersistenceError|null $error      Stable failure code.
	 */
	private function __construct(
		private bool $successful,
		private bool $changed,
		private ?RecurrencePersistenceError $error
	) {}

	/**
	 * Return one successful outcome.
	 *
	 * @param bool $changed Whether canonical storage changed.
	 */
	public static function success( bool $changed ): self {
		return new self( true, $changed, null );
	}

	/**
	 * Return one failed outcome.
	 *
	 * @param RecurrencePersistenceError $error   Stable failure code.
	 * @param bool                       $changed Whether canonical storage changed before the failure.
	 */
	public static function failure( RecurrencePersistenceError $error, bool $changed = false ): self {
		return new self( false, $changed, $error );
	}

	/**
	 * Whether the complete operation succeeded.
	 */
	public function successful(): bool {
		return $this->successful;
	}

	/**
	 * Whether canonical storage changed.
	 */
	public function changed(): bool {
		return $this->changed;
	}

	/**
	 * Return the stable failure code, when present.
	 */
	public function error(): ?RecurrencePersistenceError {
		return $this->error;
	}
}
