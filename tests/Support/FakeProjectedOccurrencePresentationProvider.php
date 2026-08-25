<?php
/**
 * Deterministic projected occurrence presentation provider.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Frontend\ProjectedOccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;

/**
 * Returns configured contexts by public key and records direct row requests.
 */
final class FakeProjectedOccurrencePresentationProvider implements ProjectedOccurrencePresentationProvider {
	/**
	 * Configured contexts keyed by public occurrence identity.
	 *
	 * @var array<string, OccurrencePresentationContext|null>
	 */
	public array $contexts = array();

	/**
	 * Direct projection rows submitted to the provider.
	 *
	 * @var list<OccurrenceReadModel>
	 */
	public array $requests = array();

	/**
	 * Resolve one configured projected context.
	 *
	 * @param OccurrenceReadModel $occurrence Active public projection row.
	 */
	public function resolve_projected( OccurrenceReadModel $occurrence ): ?OccurrencePresentationContext {
		$this->requests[] = $occurrence;

		return $this->contexts[ $occurrence->public_key ] ?? null;
	}
}
