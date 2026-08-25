<?php
/**
 * Conservative cache policy for virtual occurrence leaves.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Routing;

/**
 * Prevents stable occurrence URLs from serving stale full-page cache entries.
 */
final readonly class OccurrenceCacheController {
	/**
	 * Create the cache policy over the request's shared occurrence route.
	 *
	 * @param OccurrenceRouteController $occurrences Validated current route context.
	 */
	public function __construct(
		private OccurrenceRouteController $occurrences = new OccurrenceRouteController()
	) {}

	/** Register after the route resolves its exact current context. */
	public function register(): void {
		add_action( 'wp', array( $this, 'apply' ), 20 );
	}

	/** Apply the conservative no-store policy to a valid occurrence leaf only. */
	public function apply(): void {
		if ( null === $this->occurrences->current() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- De facto cache-plugin interoperability contract.
		}

		/**
		 * Marks the exact virtual leaf as non-cacheable in LiteSpeed Cache.
		 *
		 * The action is intentionally safe when LiteSpeed Cache is absent.
		 */
		do_action( 'litespeed_control_set_nocache', 'MiMe Simple Events occurrence leaf' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache public API hook.
		nocache_headers();
	}
}
