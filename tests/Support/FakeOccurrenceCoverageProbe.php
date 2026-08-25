<?php
/**
 * In-memory occurrence coverage probe.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceCoverageProbe;

/**
 * Returns one configured public-index gap decision.
 */
final class FakeOccurrenceCoverageProbe implements OccurrenceCoverageProbe {
	/**
	 * Number of recorded coverage probes.
	 *
	 * @var int
	 */
	public int $calls = 0;

	/**
	 * Configure the public-gap decision.
	 *
	 * @param bool $has_gap Whether a public coverage gap exists.
	 */
	public function __construct( private readonly bool $has_gap = false ) {}

	/**
	 * Record and return the configured result.
	 */
	public function has_public_gap(): bool {
		++$this->calls;

		return $this->has_gap;
	}
}
