<?php
/**
 * Optional SEO plugin canonical compatibility.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;

/**
 * Keeps supported SEO-plugin canonicals on the exact current occurrence leaf.
 */
final readonly class ThirdPartyCanonicalController {
	/**
	 * Create the adapter.
	 *
	 * @param OccurrenceRouteController $occurrences Shared exact occurrence route context.
	 */
	public function __construct(
		private OccurrenceRouteController $occurrences = new OccurrenceRouteController()
	) {}

	/** Register documented optional integration filters. */
	public function register(): void {
		add_filter( 'wpseo_canonical', array( $this, 'canonical' ), 20 );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical' ), 20 );
		add_filter( 'aioseo_canonical_url', array( $this, 'canonical' ), 20 );
	}

	/**
	 * Return the exact safe occurrence canonical or preserve the host value.
	 *
	 * @param string|false|null $canonical Existing SEO-plugin canonical value.
	 * @return string|false|null
	 */
	public function canonical( string|false|null $canonical ): string|false|null {
		$context = $this->occurrences->current();

		if ( null === $context ) {
			return $canonical;
		}

		$occurrence_url = esc_url_raw(
			$this->occurrences->canonical_url( $context ),
			array( 'http', 'https' )
		);

		return '' === $occurrence_url ? $canonical : $occurrence_url;
	}
}
