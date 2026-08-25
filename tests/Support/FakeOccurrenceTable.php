<?php
/**
 * In-memory occurrence table lifecycle test double.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceTableLifecycle;

/**
 * Records deterministic install and cleanup outcomes without a database.
 */
final class FakeOccurrenceTable implements OccurrenceTableLifecycle {
	/**
	 * Number of recorded install attempts.
	 *
	 * @var int
	 */
	public int $install_calls = 0;

	/**
	 * Number of recorded table-drop attempts.
	 *
	 * @var int
	 */
	public int $drop_calls = 0;

	/**
	 * Configure lifecycle results.
	 *
	 * @param bool $install_result Result returned by install().
	 * @param bool $drop_result    Result returned by drop().
	 * @param bool $exists_result  Result returned by exists().
	 */
	public function __construct(
		private readonly bool $install_result = true,
		private readonly bool $drop_result = true,
		private readonly bool $exists_result = true
	) {}

	/**
	 * Return the configured table-presence result.
	 */
	public function exists(): bool {
		return $this->exists_result;
	}

	/**
	 * Record one install attempt.
	 */
	public function install(): bool {
		++$this->install_calls;

		return $this->install_result;
	}

	/**
	 * Record one destructive cleanup attempt.
	 */
	public function drop(): bool {
		++$this->drop_calls;

		return $this->drop_result;
	}
}
