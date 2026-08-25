<?php
/**
 * Public occurrence presentation boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Resolves one exact occurrence without exposing projection or aggregate storage.
 */
interface OccurrencePresentationProvider {
	/**
	 * Resolve one published, password-free occurrence or fail closed.
	 *
	 * @param int    $event_id   Canonical series post ID.
	 * @param string $public_key Stable lowercase occurrence key.
	 */
	public function resolve_public( int $event_id, string $public_key ): ?OccurrencePresentationContext;
}
