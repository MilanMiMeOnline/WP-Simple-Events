<?php
/**
 * Native event archive query integration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Query;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Occurrence\OccurrencePage;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Shortcode\EventListAttributes;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use WP_Post;
use WP_Query;

/**
 * Applies shared public event rules to the main event archive query.
 */
final class EventArchiveQuery {
	/**
	 * Request-local occurrence criteria keyed by their native query object.
	 *
	 * Keeping domain objects out of WordPress query vars avoids unsafe or unstable
	 * query-cache serialization.
	 *
	 * @var array<int, EventQueryCriteria>
	 */
	private array $occurrence_criteria = array();

	/**
	 * Request-local occurrence pages keyed by their native query object.
	 *
	 * @var array<int, OccurrencePage>
	 */
	private array $occurrence_pages = array();

	/**
	 * Create the archive adapter.
	 *
	 * @param EventQueryArguments      $arguments            Shared query argument builder.
	 * @param EventArchiveSettings     $settings             Validated archive settings.
	 * @param OccurrenceReadRepository $occurrences          Occurrence-level public repository.
	 * @param OccurrenceRouteFeature   $occurrence_feature   Explicit public recurrence gate.
	 * @param OccurrenceReadiness      $occurrence_readiness Projection readiness gate.
	 */
	public function __construct(
		private readonly EventQueryArguments $arguments = new EventQueryArguments(),
		private readonly EventArchiveSettings $settings = new EventArchiveSettings(),
		private readonly OccurrenceReadRepository $occurrences = new OccurrenceReadRepository(),
		private readonly OccurrenceRouteFeature $occurrence_feature = new OccurrenceRouteFeature(),
		private readonly OccurrenceReadiness $occurrence_readiness = new OccurrenceReadiness()
	) {}

	/**
	 * Register public archive hooks.
	 */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
		add_filter( 'posts_pre_query', array( $this, 'occurrence_posts' ), 10, 2 );
	}

	/**
	 * Register allowlisted archive filter variables.
	 *
	 * @param string[] $query_vars Existing public query variables.
	 * @return string[]
	 */
	public function query_vars( array $query_vars ): array {
		$query_vars[] = 'wpse_period';
		$query_vars[] = 'wpse_category';
		$query_vars[] = 'wpse_tag';

		return array_values( array_unique( $query_vars ) );
	}

	/**
	 * Apply public visibility and event chronology to known event collections.
	 *
	 * @param WP_Query $query Current WordPress query.
	 */
	public function apply( WP_Query $query ): void {
		$is_post_type_archive = $query->is_post_type_archive( EventPostType::POST_TYPE );
		$is_taxonomy_archive  = $query->is_tax( array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG ) );

		if ( is_admin() || ! $query->is_main_query() || ( ! $is_post_type_archive && ! $is_taxonomy_archive ) ) {
			return;
		}

		$default         = $is_taxonomy_archive ? EventPeriod::ALL : $this->settings->default_period();
		$period_value    = $query->get( 'wpse_period' );
		$period          = ! $is_taxonomy_archive && is_scalar( $period_value )
			? EventPeriod::tryFrom( strtolower( (string) $period_value ) ) ?? $default
			: $default;
		$per_page        = $this->settings->per_page();
		$page_value      = $query->get( 'paged' );
		$page            = is_numeric( $page_value ) ? (int) $page_value : 1;
		$page            = $page >= 1 && $page <= EventQueryCriteria::MAX_PAGE ? $page : 1;
		$occurrence_mode = $this->occurrence_feature->enabled() && $this->occurrence_readiness->ready();
		$attributes      = EventListAttributes::from_shortcode(
			$occurrence_mode
				? $this->filter_attributes( $query, $is_taxonomy_archive )
				: array(
					'category' => $is_taxonomy_archive ? '' : $query->get( 'wpse_category' ),
					'tag'      => $is_taxonomy_archive ? '' : $query->get( 'wpse_tag' ),
				)
		);
		$criteria        = new EventQueryCriteria(
			$period,
			$per_page,
			$page,
			$attributes->category_slugs,
			$attributes->tag_slugs,
			time()
		);

		$query->set( 'wpse_period', $period->value );

		if ( $occurrence_mode ) {
			$query->set( 'posts_per_page', $per_page );
			$query->set( 'ignore_sticky_posts', true );
			$query->set( 'no_found_rows', true );
			$this->occurrence_criteria[ spl_object_id( $query ) ] = $criteria;

			return;
		}

		foreach ( $this->arguments->build( $criteria ) as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	/**
	 * Short-circuit the native SQL result with an occurrence-aware templateshell.
	 *
	 * The returned post objects exist only to keep WordPress' archive and paged
	 * request state truthful. Rendering consumes the stored occurrence page and
	 * never derives dates, URLs or totals from this shell.
	 *
	 * @param array<int, WP_Post>|null $posts Preempted posts from an earlier filter.
	 * @param WP_Query                 $query Current WordPress query.
	 * @return array<int, WP_Post>|null
	 */
	public function occurrence_posts( ?array $posts, WP_Query $query ): ?array {
		$criteria = $this->occurrence_criteria[ spl_object_id( $query ) ] ?? null;

		if ( ! $criteria instanceof EventQueryCriteria ) {
			return $posts;
		}

		try {
			$page = $this->occurrences->query( $criteria );
		} catch ( OccurrenceReadException ) {
			$page = new OccurrencePage( array(), 0, 0 );
		}

		$shell = $this->shell_posts( $page );

		if ( null === $shell ) {
			$page  = new OccurrencePage( array(), 0, 0 );
			$shell = array();
		}

		$this->occurrence_pages[ spl_object_id( $query ) ] = $page;
		$query->found_posts                                = $page->total;
		$query->max_num_pages                              = $page->total_pages;

		return $shell;
	}

	/**
	 * Return the exact occurrence page prepared for one native query.
	 *
	 * @param WP_Query $query Native main event archive query.
	 */
	public function occurrence_page( WP_Query $query ): ?OccurrencePage {
		return $this->occurrence_pages[ spl_object_id( $query ) ] ?? null;
	}

	/**
	 * Build archive filters, fixing taxonomy archives to their requested term.
	 *
	 * @param WP_Query $query               Current WordPress query.
	 * @param bool     $is_taxonomy_archive Whether this is an event taxonomy route.
	 * @return array{category: mixed, tag: mixed}
	 */
	private function filter_attributes( WP_Query $query, bool $is_taxonomy_archive ): array {
		if ( ! $is_taxonomy_archive ) {
			return array(
				'category' => $query->get( 'wpse_category' ),
				'tag'      => $query->get( 'wpse_tag' ),
			);
		}

		$taxonomy = $query->get( 'taxonomy' );
		$term     = $query->get( 'term' );

		if ( ! is_scalar( $term ) || '' === trim( (string) $term ) ) {
			$term = is_string( $taxonomy ) ? $query->get( $taxonomy ) : '';
		}

		$term = is_scalar( $term ) ? basename( str_replace( '\\', '/', (string) $term ) ) : '';

		return array(
			'category' => EventTaxonomies::CATEGORY === $taxonomy ? $term : '',
			'tag'      => EventTaxonomies::TAG === $taxonomy ? $term : '',
		);
	}

	/**
	 * Resolve public parent objects without collapsing repeated occurrence rows.
	 *
	 * @param OccurrencePage $page Exact authorized occurrence page.
	 * @return list<WP_Post>|null Null when a parent changed after the read query.
	 */
	private function shell_posts( OccurrencePage $page ): ?array {
		$shell = array();

		foreach ( $page->occurrences as $occurrence ) {
			$post = get_post( $occurrence->event_id );

			if ( ! $post instanceof WP_Post
				|| EventPostType::POST_TYPE !== $post->post_type
				|| 'publish' !== $post->post_status
				|| '' !== $post->post_password
			) {
				return null;
			}

			$shell[] = $post;
		}

		return $shell;
	}
}
