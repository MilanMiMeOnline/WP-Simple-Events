<?php
/**
 * Public occurrence-route feature decision.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Routing;

/**
 * Keeps the public recurrence read surface on one injectable decision boundary.
 */
final readonly class OccurrenceRouteFeature {
	/**
	 * Whether the public occurrence route is active.
	 *
	 * @var bool
	 */
	private bool $active;

	/**
	 * Create the feature with an optional deterministic test decision.
	 *
	 * @param bool|null $active Explicit decision, or the production default.
	 */
	public function __construct( ?bool $active = null ) {
		$this->active = $active ?? true;
	}

	/** Return whether public occurrence routing is enabled. */
	public function enabled(): bool {
		return $this->active;
	}
}
