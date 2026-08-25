<?php
/**
 * Deterministic occurrence presentation provider.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationProvider;

/**
 * Returns a configured context and records exact route requests.
 */
final class FakeOccurrencePresentationProvider implements OccurrencePresentationProvider {
	/**
	 * Configured exact context, or a simulated missing identity.
	 *
	 * @var OccurrencePresentationContext|null
	 */
	public ?OccurrencePresentationContext $context = null;

	/**
	 * Recorded exact route requests.
	 *
	 * @var list<array{event_id: int, public_key: string}>
	 */
	public array $requests = array();

	/**
	 * Resolve the configured context.
	 *
	 * @param int    $event_id   Canonical event ID.
	 * @param string $public_key Exact occurrence public key.
	 */
	public function resolve_public( int $event_id, string $public_key ): ?OccurrencePresentationContext {
		$this->requests[] = array(
			'event_id'   => $event_id,
			'public_key' => $public_key,
		);

		return $this->context;
	}
}
