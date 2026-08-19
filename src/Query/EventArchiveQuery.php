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
use MiMe\WPSimpleEvents\Shortcode\EventListAttributes;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use WP_Query;

/**
 * Applies shared public event rules to the main event archive query.
 */
final readonly class EventArchiveQuery {
	/**
	 * Create the archive adapter.
	 *
	 * @param EventQueryArguments  $arguments Shared query argument builder.
	 * @param EventArchiveSettings $settings  Validated archive settings.
	 */
	public function __construct(
		private EventQueryArguments $arguments = new EventQueryArguments(),
		private EventArchiveSettings $settings = new EventArchiveSettings()
	) {}

	/**
	 * Register public archive hooks.
	 */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
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

		$default      = $is_taxonomy_archive ? EventPeriod::ALL : $this->settings->default_period();
		$period_value = $query->get( 'wpse_period' );
		$period       = ! $is_taxonomy_archive && is_scalar( $period_value )
			? EventPeriod::tryFrom( strtolower( (string) $period_value ) ) ?? $default
			: $default;
		$per_page     = $this->settings->per_page();
		$page_value   = $query->get( 'paged' );
		$page         = is_numeric( $page_value ) ? (int) $page_value : 1;
		$page         = $page >= 1 && $page <= EventQueryCriteria::MAX_PAGE ? $page : 1;
		$attributes   = EventListAttributes::from_shortcode(
			array(
				'category' => $is_taxonomy_archive ? '' : $query->get( 'wpse_category' ),
				'tag'      => $is_taxonomy_archive ? '' : $query->get( 'wpse_tag' ),
			)
		);
		$criteria     = new EventQueryCriteria(
			$period,
			$per_page,
			$page,
			$attributes->category_slugs,
			$attributes->tag_slugs,
			time()
		);

		$query->set( 'wpse_period', $period->value );

		foreach ( $this->arguments->build( $criteria ) as $key => $value ) {
			$query->set( $key, $value );
		}
	}
}
