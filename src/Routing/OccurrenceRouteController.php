<?php
/**
 * Virtual public occurrence leaf routing.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Routing;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationResolver;
use WP_Query;

/**
 * Resolves an explicitly enabled virtual leaf into one shared occurrence context.
 */
final class OccurrenceRouteController {
	/** Public query variable containing one exact occurrence key. */
	public const QUERY_VAR = OccurrenceRouteUrlBuilder::QUERY_VAR;

	/**
	 * Exact presentation context resolved during this request.
	 *
	 * @var OccurrencePresentationContext|null
	 */
	private ?OccurrencePresentationContext $current = null;

	/**
	 * Create the route controller.
	 *
	 * @param OccurrencePresentationProvider $presentations Exact public context provider.
	 * @param EventArchiveSettings           $settings      Validated event archive route.
	 * @param OccurrenceRouteUrlBuilder      $urls          Canonical occurrence URL builder.
	 */
	public function __construct(
		private readonly OccurrencePresentationProvider $presentations = new OccurrencePresentationResolver(),
		private readonly EventArchiveSettings $settings = new EventArchiveSettings(),
		private readonly OccurrenceRouteUrlBuilder $urls = new OccurrenceRouteUrlBuilder()
	) {}

	/** Register the public WordPress route hooks. */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'init', array( $this, 'add_rewrite_rule' ), 15 );
		add_action( 'wp', array( $this, 'resolve_current' ) );
		add_filter( 'redirect_canonical', array( $this, 'prevent_parent_redirect' ), 10, 2 );
	}

	/**
	 * Register the one allowlisted occurrence query variable.
	 *
	 * @param string[] $query_vars Existing public query variables.
	 * @return string[]
	 */
	public function query_vars( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return array_values( array_unique( $query_vars ) );
	}

	/** Register the strict pretty-permalink leaf rule. */
	public function add_rewrite_rule(): void {
		$archive = preg_quote( $this->settings->slug(), '/' );

		add_rewrite_rule(
			'^' . $archive . '/([^/]+)/occurrence/([a-f0-9]{32})/?$',
			'index.php?' . EventPostType::POST_TYPE . '=$matches[1]&' . self::QUERY_VAR . '=$matches[2]',
			'top'
		);
	}

	/** Resolve the global main query after WordPress has selected its event post. */
	public function resolve_current(): void {
		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$this->resolve( $wp_query );
		}
	}

	/**
	 * Resolve one main query or convert an invalid occurrence request to a 404.
	 *
	 * @param WP_Query $query WordPress main query.
	 */
	public function resolve( WP_Query $query ): ?OccurrencePresentationContext {
		$this->current = null;
		$key           = $query->get( self::QUERY_VAR );

		if ( '' === $key ) {
			return null;
		}

		if ( ! is_string( $key )
			|| 1 !== preg_match( '/^[a-f0-9]{32}$/D', $key )
			|| ! $query->is_singular( EventPostType::POST_TYPE )
		) {
			return $this->not_found( $query );
		}

		$event_id = $query->get_queried_object_id();
		$context  = $this->presentations->resolve_public( $event_id, $key );

		if ( null === $context
			|| $context->series->event->ID !== $event_id
			|| $context->occurrence->event_id !== $event_id
			|| ! hash_equals( $context->occurrence->public_key, $key )
		) {
			return $this->not_found( $query );
		}

		$this->current = $context;

		return $context;
	}

	/** Return the exact context resolved for the current request, if any. */
	public function current(): ?OccurrencePresentationContext {
		return $this->current;
	}

	/**
	 * Build the canonical pretty or plain-permalink URL for one resolved context.
	 *
	 * @param OccurrencePresentationContext $context Exact occurrence context.
	 */
	public function canonical_url( OccurrencePresentationContext $context ): string {
		return $this->urls->build( $context->series->permalink, $context->occurrence->public_key );
	}

	/**
	 * Keep an occurrence request from redirecting to and revealing its series root.
	 *
	 * @param string|false $redirect_url  Proposed canonical destination.
	 * @param string       $requested_url Original request URL.
	 * @return string|false
	 */
	public function prevent_parent_redirect( string|false $redirect_url, string $requested_url ): string|false {
		unset( $requested_url );

		global $wp_query;

		if ( $wp_query instanceof WP_Query && '' !== $wp_query->get( self::QUERY_VAR ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Convert one invalid leaf into a non-cacheable 404 response.
	 *
	 * @param WP_Query $query Main WordPress query.
	 */
	private function not_found( WP_Query $query ): null {
		$query->set_404();
		status_header( 404 );
		nocache_headers();

		return null;
	}
}
