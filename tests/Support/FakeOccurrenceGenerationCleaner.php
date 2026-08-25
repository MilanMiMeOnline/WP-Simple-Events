<?php
/**
 * In-memory inactive-generation cleaner test double.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceGenerationCleaner;

/**
 * Captures cleanup bounds and returns a configured result.
 */
final class FakeOccurrenceGenerationCleaner implements OccurrenceGenerationCleaner {
	/**
	 * Captured cleanup requests.
	 *
	 * @var list<array{cutoff_utc: int, limit: int}>
	 */
	public array $calls = array();

	/**
	 * Configure the removed row count, or null for failure.
	 *
	 * @param int|null $result Cleanup result.
	 */
	public function __construct(
		private readonly ?int $result = 0
	) {}

	/**
	 * Capture one bounded cleanup request.
	 *
	 * @param int $cutoff_utc Cleanup age boundary.
	 * @param int $limit      Cleanup batch size.
	 */
	public function clean_before( int $cutoff_utc, int $limit ): ?int {
		$this->calls[] = array(
			'cutoff_utc' => $cutoff_utc,
			'limit'      => $limit,
		);

		return $this->result;
	}
}
