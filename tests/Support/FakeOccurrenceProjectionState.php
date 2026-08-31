<?php
/**
 * In-memory derived projection-state lifecycle test double.
 *
 * @package MiMe\WPSimpleEvents\Tests
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionStateLifecycle;

/** Records derived-state reset requests without mutating post metadata. */
final class FakeOccurrenceProjectionState implements OccurrenceProjectionStateLifecycle {
	/**
	 * Number of recorded reset requests.
	 *
	 * @var int
	 */
	public int $reset_calls = 0;

	/** Record one reset request. */
	public function reset(): void {
		++$this->reset_calls;
	}
}
