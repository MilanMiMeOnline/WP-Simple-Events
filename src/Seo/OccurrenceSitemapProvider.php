<?php
/**
 * WordPress Core occurrence sitemap provider.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationResolver;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use WP_Sitemaps_Provider;

/**
 * Discovers only finite, active and exactly validated recurring occurrence leaves.
 */
final class OccurrenceSitemapProvider extends WP_Sitemaps_Provider {
	/** Hard query ceiling independent from the filterable Core default. */
	private const MAX_PAGE_SIZE = 100;

	/**
	 * Create the Core provider over shared public-read boundaries.
	 *
	 * @param OccurrenceReadRepository       $occurrences  Bounded projection reader.
	 * @param OccurrencePresentationProvider $presentations Exact public identity resolver.
	 * @param OccurrenceRouteController      $routes       Canonical occurrence URL builder.
	 */
	public function __construct(
		private readonly OccurrenceReadRepository $occurrences = new OccurrenceReadRepository(),
		private readonly OccurrencePresentationProvider $presentations = new OccurrencePresentationResolver(),
		private readonly OccurrenceRouteController $routes = new OccurrenceRouteController()
	) {
		$this->name        = 'occurrences';
		$this->object_type = 'wpse_occurrence';
	}

	/** Register this provider through WordPress Core's public sitemap API. */
	public function register(): void {
		add_action( 'init', array( $this, 'register_provider' ), 20 );
	}

	/** Register the provider when the Core sitemap component is available. */
	public function register_provider(): void {
		if ( function_exists( 'wp_register_sitemap_provider' ) ) {
			wp_register_sitemap_provider( $this->name, $this );
		}
	}

	/**
	 * Return one sitemap page containing only strict occurrence canonicals.
	 *
	 * @param mixed  $page_num       One-based page number supplied by Core.
	 * @param string $object_subtype Unsupported subtype, which must remain empty.
	 * @return list<array{loc: string}>
	 */
	public function get_url_list( $page_num, $object_subtype = '' ): array { // phpcs:ignore Squiz.Commenting.FunctionComment.ScalarTypeHintMissing -- Signature must remain compatible with WordPress Core's untyped abstract method.
		$page      = $this->page_number( $page_num );
		$page_size = $this->page_size();

		if ( null === $page || null === $page_size || '' !== $object_subtype ) {
			return array();
		}

		try {
			$candidates = $this->occurrences->query_sitemap( $page_size, $page );
		} catch ( InvalidArgumentException | OccurrenceReadException ) {
			return array();
		}

		$entries = array();
		$seen    = array();

		foreach ( $candidates->occurrences as $candidate ) {
			$context = $this->presentations->resolve_public( $candidate->event_id, $candidate->public_key );

			if ( null === $context
				|| $context->series->event->ID !== $candidate->event_id
				|| $context->occurrence->event_id !== $candidate->event_id
				|| ! hash_equals( $context->occurrence->public_key, $candidate->public_key )
			) {
				continue;
			}

			$canonical = $this->routes->canonical_url( $context );

			if ( ! $this->is_http_url( $canonical ) || isset( $seen[ $canonical ] ) ) {
				continue;
			}

			$seen[ $canonical ] = true;
			$entries[]          = array( 'loc' => $canonical );
		}

		return $entries;
	}

	/**
	 * Return the exact number of bounded Core sitemap pages.
	 *
	 * @param string $object_subtype Unsupported subtype, which must remain empty.
	 */
	public function get_max_num_pages( $object_subtype = '' ): int { // phpcs:ignore Squiz.Commenting.FunctionComment.ScalarTypeHintMissing -- Signature must remain compatible with WordPress Core's untyped abstract method.
		$page_size = $this->page_size();

		if ( null === $page_size || '' !== $object_subtype ) {
			return 0;
		}

		try {
			$total = $this->occurrences->count_sitemap( $page_size );
		} catch ( InvalidArgumentException | OccurrenceReadException ) {
			return 0;
		}

		return 0 === $total ? 0 : (int) ceil( $total / $page_size );
	}

	/** Return the Core-filtered page size without exceeding the plugin ceiling. */
	private function page_size(): ?int {
		$maximum = wp_sitemaps_get_max_urls( $this->object_type );

		return $maximum > 0 ? min( self::MAX_PAGE_SIZE, $maximum ) : null;
	}

	/**
	 * Normalize one Core page value without accepting floats or weak numerics.
	 *
	 * @param mixed $page_num Untrusted query-derived page value.
	 */
	private function page_number( mixed $page_num ): ?int {
		if ( is_int( $page_num ) ) {
			return $page_num > 0 ? $page_num : null;
		}

		if ( ! is_string( $page_num ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $page_num ) ) {
			return null;
		}

		$page = filter_var( $page_num, FILTER_VALIDATE_INT );

		return false !== $page ? $page : null;
	}

	/**
	 * Accept only a complete public HTTP(S) URL.
	 *
	 * @param string $url Candidate canonical URL.
	 */
	private function is_http_url( string $url ): bool {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		return false !== filter_var( $url, FILTER_VALIDATE_URL )
			&& is_string( $scheme )
			&& in_array( strtolower( $scheme ), array( 'http', 'https' ), true );
	}
}
