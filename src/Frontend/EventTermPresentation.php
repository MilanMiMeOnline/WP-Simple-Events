<?php
/**
 * Public event-term presentation value.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Keeps taxonomy storage objects outside host adapters.
 */
final readonly class EventTermPresentation {
	/**
	 * Store one named public term destination.
	 *
	 * @param string $name Public term name.
	 * @param string $url  Public term URL.
	 */
	public function __construct(
		public string $name,
		public string $url
	) {}
}
