<?php
/**
 * Mutable WordPress state for isolated unit tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

/**
 * Supplies deterministic roles and authorization decisions to function doubles.
 */
final class WordPressState {
	/**
	 * Test roles keyed by WordPress role name.
	 *
	 * @var array<string, FakeRole>
	 */
	private static array $roles = array();

	/**
	 * Configured current-user capability decision.
	 *
	 * @var bool
	 */
	private static bool $current_user_can = false;

	/**
	 * Stored post metadata keyed by post ID and meta key.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $post_meta = array();

	/**
	 * Stored term metadata keyed by term ID and meta key.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $term_meta = array();

	/**
	 * Configured post objects keyed by ID.
	 *
	 * @var array<int, \WP_Post>
	 */
	private static array $posts = array();

	/**
	 * Last arguments submitted to get_posts().
	 *
	 * @var array<string, mixed>
	 */
	private static array $last_get_posts_arguments = array();

	/**
	 * Public post URLs keyed by ID.
	 *
	 * @var array<int, string>
	 */
	private static array $permalinks = array();

	/**
	 * Featured-image URLs keyed by ID.
	 *
	 * @var array<int, string>
	 */
	private static array $image_urls = array();

	/**
	 * Featured-image alternative text keyed by post ID.
	 *
	 * @var array<int, string>
	 */
	private static array $image_alts = array();

	/**
	 * Configured term objects keyed by term ID.
	 *
	 * @var array<int, \WP_Term>
	 */
	private static array $terms = array();

	/**
	 * Public term URLs keyed by term ID.
	 *
	 * @var array<int, string>
	 */
	private static array $term_links = array();

	/**
	 * Deterministic filter callbacks keyed by hook.
	 *
	 * @var array<string, callable>
	 */
	private static array $filters = array();

	/**
	 * Explicit option values keyed by site and option name.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $options = array();

	/**
	 * Whether tests emulate a multisite network.
	 *
	 * @var bool
	 */
	private static bool $multisite = false;

	/**
	 * Configured multisite IDs.
	 *
	 * @var list<int>
	 */
	private static array $site_ids = array( 1 );

	/**
	 * Current deterministic site ID.
	 *
	 * @var int
	 */
	private static int $current_site_id = 1;

	/**
	 * Site stack used by switch and restore doubles.
	 *
	 * @var list<int>
	 */
	private static array $site_stack = array();

	/**
	 * Site IDs visited through switch_to_blog().
	 *
	 * @var list<int>
	 */
	private static array $switched_site_ids = array();

	/**
	 * Current singular event request state.
	 *
	 * @var bool
	 */
	private static bool $singular_event = false;

	/**
	 * Current event archive request state.
	 *
	 * @var bool
	 */
	private static bool $event_archive = false;

	/**
	 * Current event taxonomy archive, or an empty string for another request.
	 *
	 * @var string
	 */
	private static string $event_taxonomy_archive = '';

	/**
	 * Deterministic public archive title.
	 *
	 * @var string
	 */
	private static string $archive_title = 'Events';

	/**
	 * Whether the active test theme is a full block theme.
	 *
	 * @var bool
	 */
	private static bool $block_theme = false;

	/**
	 * Theme-owned PHP template returned by locate_template().
	 *
	 * @var string
	 */
	private static string $theme_template = '';

	/**
	 * Final template returned by locate_block_template().
	 *
	 * @var string
	 */
	private static string $block_template_result = '';

	/**
	 * Recorded block-template lookup arguments.
	 *
	 * @var list<array{template: string, type: string, templates: list<string>}>
	 */
	private static array $block_template_calls = array();

	/**
	 * Registered dynamic block names.
	 *
	 * @var list<string>
	 */
	private static array $registered_block_types = array();

	/**
	 * Registered plugin block-template names.
	 *
	 * @var list<string>
	 */
	private static array $registered_block_templates = array();

	/**
	 * Core sitemap providers registered during a test.
	 *
	 * @var array<string, \WP_Sitemaps_Provider>
	 */
	private static array $sitemap_providers = array();

	/**
	 * Elementor Theme Builder output keyed by core location.
	 *
	 * @var array<string, string>
	 */
	private static array $elementor_locations = array();

	/**
	 * Current queried post ID.
	 *
	 * @var int
	 */
	private static int $queried_object_id = 0;

	/**
	 * Last post data submitted to wp_insert_post().
	 *
	 * @var array<string, mixed>
	 */
	private static array $inserted_post_data = array();

	/**
	 * Post IDs deleted during a test.
	 *
	 * @var list<int>
	 */
	private static array $deleted_post_ids = array();

	/**
	 * Post IDs for which an explicit revision save was requested.
	 *
	 * @var list<int>
	 */
	private static array $saved_post_revision_ids = array();

	/**
	 * Stored taxonomy term IDs keyed by post and taxonomy.
	 *
	 * @var array<int, array<string, list<int>>>
	 */
	private static array $post_terms = array();

	/**
	 * Recorded metadata cache priming calls.
	 *
	 * @var list<array{type: string, ids: list<int>}>
	 */
	private static array $meta_cache_calls = array();

	/**
	 * Recorded object-term cache priming calls.
	 *
	 * @var list<array{ids: list<int>, post_type: string}>
	 */
	private static array $object_term_cache_calls = array();

	/**
	 * Standalone taxonomy term IDs keyed by taxonomy.
	 *
	 * @var array<string, list<int>>
	 */
	private static array $taxonomy_terms = array();

	/**
	 * Deleted taxonomy terms keyed by taxonomy.
	 *
	 * @var array<string, list<int>>
	 */
	private static array $deleted_terms = array();

	/**
	 * Whether taxonomy operations should return an error.
	 *
	 * @var bool
	 */
	private static bool $fail_term_operations = false;

	/**
	 * Whether metadata writes and deletes should fail.
	 *
	 * @var bool
	 */
	private static bool $fail_meta_operations = false;

	/**
	 * Number of soft or hard rewrite flushes requested.
	 *
	 * @var int
	 */
	private static int $rewrite_flushes = 0;

	/**
	 * Registered rewrite rules in call order.
	 *
	 * @var list<array{regex: string, query: string, after: string}>
	 */
	private static array $rewrite_rules = array();

	/**
	 * Last HTTP response status selected by a test request.
	 *
	 * @var int
	 */
	private static int $response_status = 200;

	/**
	 * Number of non-cacheable response-header requests.
	 *
	 * @var int
	 */
	private static int $nocache_headers = 0;

	/**
	 * Scheduled one-off timestamps keyed by hook.
	 *
	 * @var array<string, list<int>>
	 */
	private static array $scheduled_hooks = array();

	/**
	 * Deterministically registered post type keys.
	 *
	 * @var array<string, true>
	 */
	private static array $registered_post_types = array();

	/**
	 * Post type keys unregistered during a test.
	 *
	 * @var list<string>
	 */
	private static array $unregistered_post_types = array();

	/**
	 * Deterministically registered taxonomy keys.
	 *
	 * @var array<string, true>
	 */
	private static array $registered_taxonomies = array();

	/**
	 * Taxonomy keys unregistered during a test.
	 *
	 * @var list<string>
	 */
	private static array $unregistered_taxonomies = array();

	/**
	 * Reset mutable state before a test.
	 */
	public static function reset(): void {
		self::$roles                      = array();
		self::$current_user_can           = false;
		self::$post_meta                  = array();
		self::$term_meta                  = array();
		self::$posts                      = array();
		self::$last_get_posts_arguments   = array();
		self::$permalinks                 = array();
		self::$image_urls                 = array();
		self::$image_alts                 = array();
		self::$terms                      = array();
		self::$term_links                 = array();
		self::$filters                    = array();
		self::$options                    = array();
		self::$multisite                  = false;
		self::$site_ids                   = array( 1 );
		self::$current_site_id            = 1;
		self::$site_stack                 = array();
		self::$switched_site_ids          = array();
		self::$singular_event             = false;
		self::$event_archive              = false;
		self::$event_taxonomy_archive     = '';
		self::$archive_title              = 'Events';
		self::$block_theme                = false;
		self::$theme_template             = '';
		self::$block_template_result      = '';
		self::$block_template_calls       = array();
		self::$registered_block_types     = array();
		self::$registered_block_templates = array();
		self::$sitemap_providers          = array();
		self::$elementor_locations        = array();
		self::$queried_object_id          = 0;
		self::$inserted_post_data         = array();
		self::$deleted_post_ids           = array();
		self::$saved_post_revision_ids    = array();
		self::$post_terms                 = array();
		self::$meta_cache_calls           = array();
		self::$object_term_cache_calls    = array();
		self::$taxonomy_terms             = array();
		self::$deleted_terms              = array();
		self::$fail_term_operations       = false;
		self::$fail_meta_operations       = false;
		self::$rewrite_flushes            = 0;
		self::$rewrite_rules              = array();
		self::$response_status            = 200;
		self::$nocache_headers            = 0;
		self::$scheduled_hooks            = array();
		self::$registered_post_types      = array();
		self::$unregistered_post_types    = array();
		self::$registered_taxonomies      = array();
		self::$unregistered_taxonomies    = array();
	}

	/**
	 * Record one metadata cache priming call.
	 *
	 * @param string $type Metadata object type.
	 * @param int[]  $ids  Object IDs.
	 */
	public static function record_meta_cache( string $type, array $ids ): void {
		self::$meta_cache_calls[] = array(
			'type' => $type,
			'ids'  => array_values( $ids ),
		);
	}

	/**
	 * Return recorded metadata cache calls.
	 *
	 * @return list<array{type: string, ids: list<int>}>
	 */
	public static function meta_cache_calls(): array {
		return self::$meta_cache_calls;
	}

	/**
	 * Record one object-term cache priming call.
	 *
	 * @param int[]  $ids       Object IDs.
	 * @param string $post_type WordPress post type.
	 */
	public static function record_object_term_cache( array $ids, string $post_type ): void {
		self::$object_term_cache_calls[] = array(
			'ids'       => array_values( $ids ),
			'post_type' => $post_type,
		);
	}

	/**
	 * Return recorded object-term cache calls.
	 *
	 * @return list<array{ids: list<int>, post_type: string}>
	 */
	public static function object_term_cache_calls(): array {
		return self::$object_term_cache_calls;
	}

	/**
	 * Record one deterministic get_posts() request.
	 *
	 * @param array<string, mixed> $arguments Query arguments.
	 */
	public static function record_get_posts_arguments( array $arguments ): void {
		self::$last_get_posts_arguments = $arguments;
	}

	/**
	 * Return the last deterministic get_posts() request.
	 *
	 * @return array<string, mixed>
	 */
	public static function last_get_posts_arguments(): array {
		return self::$last_get_posts_arguments;
	}

	/**
	 * Insert one deterministic post and return its fixed ID.
	 *
	 * @param array<string, mixed> $post_data Submitted post fields.
	 */
	public static function insert_post( array $post_data ): int {
		$post_id                  = 1001;
		self::$inserted_post_data = $post_data;
		$post_data['ID']          = $post_id;
		self::$posts[ $post_id ]  = new \WP_Post( $post_data );

		return $post_id;
	}

	/**
	 * Return the last submitted post fields.
	 *
	 * @return array<string, mixed>
	 */
	public static function inserted_post_data(): array {
		return self::$inserted_post_data;
	}

	/**
	 * Record a permanent post deletion.
	 *
	 * @param int $post_id Deleted post ID.
	 */
	public static function delete_post( int $post_id ): void {
		self::$deleted_post_ids[] = $post_id;
		unset( self::$posts[ $post_id ], self::$post_meta[ $post_id ], self::$post_terms[ $post_id ] );
	}

	/**
	 * Return post IDs deleted during this test.
	 *
	 * @return list<int>
	 */
	public static function deleted_post_ids(): array {
		return self::$deleted_post_ids;
	}

	/**
	 * Record an explicit post revision save.
	 *
	 * @param int $post_id Canonical post ID.
	 */
	public static function save_post_revision( int $post_id ): void {
		self::$saved_post_revision_ids[] = $post_id;
	}

	/**
	 * Return post IDs sent through the revision boundary.
	 *
	 * @return list<int>
	 */
	public static function saved_post_revision_ids(): array {
		return self::$saved_post_revision_ids;
	}

	/**
	 * Return configured post IDs for one post type.
	 *
	 * @param string   $post_type Requested post type.
	 * @param int      $limit     Maximum number of IDs.
	 * @param int      $offset    Starting offset.
	 * @param string[] $statuses Optional publication-status allowlist.
	 * @return list<int>
	 */
	public static function post_ids( string $post_type, int $limit, int $offset = 0, array $statuses = array() ): array {
		$ids = array();

		foreach ( self::$posts as $post ) {
			if (
				$post_type === $post->post_type
				&& ( array() === $statuses || in_array( $post->post_status, $statuses, true ) )
			) {
				$ids[] = $post->ID;
			}
		}

		sort( $ids, SORT_NUMERIC );

		return array_slice( $ids, $offset, $limit );
	}

	/**
	 * Count configured posts by status for one post type.
	 *
	 * @param string $post_type Requested post type.
	 * @return array<string, int>
	 */
	public static function post_status_counts( string $post_type ): array {
		$counts = array();

		foreach ( self::$posts as $post ) {
			if ( $post_type === $post->post_type ) {
				$counts[ $post->post_status ] = ( $counts[ $post->post_status ] ?? 0 ) + 1;
			}
		}

		return $counts;
	}

	/**
	 * Store taxonomy term IDs for a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param int[]  $term_ids Term IDs.
	 */
	public static function set_post_terms( int $post_id, string $taxonomy, array $term_ids ): void {
		self::$post_terms[ $post_id ][ $taxonomy ] = array_values( $term_ids );
	}

	/**
	 * Return taxonomy term IDs for a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return list<int>
	 */
	public static function post_terms( int $post_id, string $taxonomy ): array {
		return self::$post_terms[ $post_id ][ $taxonomy ] ?? array();
	}

	/**
	 * Configure standalone terms for one taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int[]  $term_ids Term IDs.
	 */
	public static function set_taxonomy_terms( string $taxonomy, array $term_ids ): void {
		self::$taxonomy_terms[ $taxonomy ] = array_values( $term_ids );
	}

	/**
	 * Return a bounded list of standalone taxonomy term IDs.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $limit    Maximum number of IDs.
	 * @return list<int>
	 */
	public static function taxonomy_terms( string $taxonomy, int $limit ): array {
		return array_slice( self::$taxonomy_terms[ $taxonomy ] ?? array(), 0, $limit );
	}

	/**
	 * Delete one standalone taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $term_id  Term ID.
	 */
	public static function delete_term( string $taxonomy, int $term_id ): void {
		self::$taxonomy_terms[ $taxonomy ]  = array_values(
			array_filter(
				self::$taxonomy_terms[ $taxonomy ] ?? array(),
				static fn ( int $stored_id ): bool => $term_id !== $stored_id
			)
		);
		self::$deleted_terms[ $taxonomy ][] = $term_id;
	}

	/**
	 * Return deleted term IDs for one taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return list<int>
	 */
	public static function deleted_terms( string $taxonomy ): array {
		return self::$deleted_terms[ $taxonomy ] ?? array();
	}

	/**
	 * Configure deterministic taxonomy failures.
	 *
	 * @param bool $fail Whether term reads/writes should fail.
	 */
	public static function fail_term_operations( bool $fail ): void {
		self::$fail_term_operations = $fail;
	}

	/**
	 * Return the configured taxonomy failure state.
	 */
	public static function term_operations_fail(): bool {
		return self::$fail_term_operations;
	}

	/**
	 * Configure deterministic metadata persistence failures.
	 *
	 * @param bool $fail Whether metadata writes/deletes should fail.
	 */
	public static function fail_meta_operations( bool $fail ): void {
		self::$fail_meta_operations = $fail;
	}

	/**
	 * Return the configured metadata failure state.
	 */
	public static function meta_operations_fail(): bool {
		return self::$fail_meta_operations;
	}

	/**
	 * Store a post and its optional public resources.
	 *
	 * @param \WP_Post $post       Post object.
	 * @param string   $permalink  Public URL.
	 * @param string   $image_url  Featured-image URL.
	 * @param string   $image_alt  Featured-image alternative text.
	 */
	public static function add_post( \WP_Post $post, string $permalink = '', string $image_url = '', string $image_alt = '' ): void {
		self::$posts[ $post->ID ]      = $post;
		self::$permalinks[ $post->ID ] = $permalink;
		self::$image_urls[ $post->ID ] = $image_url;
		self::$image_alts[ $post->ID ] = $image_alt;
	}

	/**
	 * Register one deterministic Core sitemap provider.
	 *
	 * @param string                $name     Provider name.
	 * @param \WP_Sitemaps_Provider $provider Provider instance.
	 */
	public static function register_sitemap_provider( string $name, \WP_Sitemaps_Provider $provider ): bool {
		if ( '' === $name || isset( self::$sitemap_providers[ $name ] ) ) {
			return false;
		}

		self::$sitemap_providers[ $name ] = $provider;

		return true;
	}

	/**
	 * Retrieve one deterministic Core sitemap provider.
	 *
	 * @param string $name Provider name.
	 */
	public static function sitemap_provider( string $name ): ?\WP_Sitemaps_Provider {
		return self::$sitemap_providers[ $name ] ?? null;
	}

	/**
	 * Retrieve one configured post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function post( int $post_id ): ?\WP_Post {
		return self::$posts[ $post_id ] ?? null;
	}

	/**
	 * Retrieve a post by its deterministic root path and type.
	 *
	 * @param string $path      Root-level post slug.
	 * @param string $post_type Requested post type.
	 */
	public static function post_by_path( string $path, string $post_type ): ?\WP_Post {
		foreach ( self::$posts as $post ) {
			if ( $post_type === $post->post_type && $path === $post->post_name ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Record one rewrite-rule flush.
	 */
	public static function record_rewrite_flush(): void {
		++self::$rewrite_flushes;
	}

	/**
	 * Return the number of rewrite-rule flushes.
	 */
	public static function rewrite_flushes(): int {
		return self::$rewrite_flushes;
	}

	/**
	 * Record one deterministic rewrite rule.
	 *
	 * @param string $regex Regular expression without delimiters.
	 * @param string $query Internal WordPress query mapping.
	 * @param string $after Rule priority group.
	 */
	public static function add_rewrite_rule( string $regex, string $query, string $after ): void {
		self::$rewrite_rules[] = array(
			'regex' => $regex,
			'query' => $query,
			'after' => $after,
		);
	}

	/**
	 * Return registered deterministic rewrite rules.
	 *
	 * @return list<array{regex: string, query: string, after: string}>
	 */
	public static function rewrite_rules(): array {
		return self::$rewrite_rules;
	}

	/**
	 * Record one deterministic HTTP status.
	 *
	 * @param int $status HTTP status code.
	 */
	public static function set_response_status( int $status ): void {
		self::$response_status = $status;
	}

	/** Return the deterministic HTTP status. */
	public static function response_status(): int {
		return self::$response_status;
	}

	/** Record one non-cacheable header request. */
	public static function record_nocache_headers(): void {
		++self::$nocache_headers;
	}

	/** Return the number of non-cacheable header requests. */
	public static function nocache_header_requests(): int {
		return self::$nocache_headers;
	}

	/**
	 * Record one scheduled event.
	 *
	 * @param string $hook      Scheduled hook.
	 * @param int    $timestamp Unix timestamp.
	 */
	public static function schedule_hook( string $hook, int $timestamp ): void {
		self::$scheduled_hooks[ $hook ][] = $timestamp;
	}

	/**
	 * Return the earliest timestamp for one hook, or false.
	 *
	 * @param string $hook Scheduled hook.
	 */
	public static function next_scheduled( string $hook ): int|false {
		$timestamps = self::$scheduled_hooks[ $hook ] ?? array();

		return array() === $timestamps ? false : min( $timestamps );
	}

	/**
	 * Remove every scheduled event for one hook.
	 *
	 * @param string $hook Scheduled hook.
	 */
	public static function clear_scheduled( string $hook ): void {
		unset( self::$scheduled_hooks[ $hook ] );
	}

	/**
	 * Count scheduled events for one hook.
	 *
	 * @param string $hook Scheduled hook.
	 */
	public static function scheduled_count( string $hook ): int {
		return count( self::$scheduled_hooks[ $hook ] ?? array() );
	}

	/**
	 * Record one post type registration.
	 *
	 * @param string $post_type Registered post type key.
	 */
	public static function register_post_type( string $post_type ): void {
		self::$registered_post_types[ $post_type ] = true;
	}

	/**
	 * Determine whether a post type is registered.
	 *
	 * @param string $post_type Requested post type key.
	 */
	public static function post_type_exists( string $post_type ): bool {
		return isset( self::$registered_post_types[ $post_type ] );
	}

	/**
	 * Record one post type unregistration.
	 *
	 * @param string $post_type Unregistered post type key.
	 */
	public static function unregister_post_type( string $post_type ): void {
		unset( self::$registered_post_types[ $post_type ] );
		self::$unregistered_post_types[] = $post_type;
	}

	/**
	 * Return post type keys unregistered during a test.
	 *
	 * @return list<string>
	 */
	public static function unregistered_post_types(): array {
		return self::$unregistered_post_types;
	}

	/**
	 * Record one taxonomy registration.
	 *
	 * @param string $taxonomy Registered taxonomy key.
	 */
	public static function register_taxonomy( string $taxonomy ): void {
		self::$registered_taxonomies[ $taxonomy ] = true;
	}

	/**
	 * Determine whether a taxonomy is registered.
	 *
	 * @param string $taxonomy Requested taxonomy key.
	 */
	public static function taxonomy_exists( string $taxonomy ): bool {
		return isset( self::$registered_taxonomies[ $taxonomy ] );
	}

	/**
	 * Record one taxonomy unregistration.
	 *
	 * @param string $taxonomy Unregistered taxonomy key.
	 */
	public static function unregister_taxonomy( string $taxonomy ): void {
		unset( self::$registered_taxonomies[ $taxonomy ] );
		self::$unregistered_taxonomies[] = $taxonomy;
	}

	/**
	 * Return taxonomy keys unregistered during a test.
	 *
	 * @return list<string>
	 */
	public static function unregistered_taxonomies(): array {
		return self::$unregistered_taxonomies;
	}

	/**
	 * Retrieve a configured public post URL.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function permalink( int $post_id ): string {
		return self::$permalinks[ $post_id ] ?? '';
	}

	/**
	 * Retrieve a configured featured-image URL.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function image_url( int $post_id ): string {
		return self::$image_urls[ $post_id ] ?? '';
	}

	/**
	 * Retrieve configured featured-image alternative text.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function image_alt( int $post_id ): string {
		return self::$image_alts[ $post_id ] ?? '';
	}

	/**
	 * Store one deterministic term and its public URL.
	 *
	 * @param \WP_Term $term      Term object.
	 * @param string   $permalink Public term URL.
	 */
	public static function add_term( \WP_Term $term, string $permalink ): void {
		self::$terms[ $term->term_id ]      = $term;
		self::$term_links[ $term->term_id ] = $permalink;
	}

	/**
	 * Retrieve one deterministic term.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function term( int $term_id ): ?\WP_Term {
		return self::$terms[ $term_id ] ?? null;
	}

	/**
	 * Retrieve one deterministic public term URL.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function term_link( int $term_id ): string {
		return self::$term_links[ $term_id ] ?? '';
	}

	/**
	 * Configure one deterministic WordPress filter callback.
	 *
	 * @param string   $hook_name Filter name.
	 * @param callable $callback  Filter callback.
	 */
	public static function set_filter( string $hook_name, callable $callback ): void {
		self::$filters[ $hook_name ] = $callback;
	}

	/**
	 * Apply a configured filter or preserve its original value.
	 *
	 * @param string $hook_name Filter name.
	 * @param mixed  $value     Original value.
	 * @param mixed  ...$args   Additional filter arguments.
	 */
	public static function apply_filter( string $hook_name, mixed $value, mixed ...$args ): mixed {
		$callback = self::$filters[ $hook_name ] ?? null;

		return is_callable( $callback ) ? $callback( $value, ...$args ) : $value;
	}

	/**
	 * Configure one option value.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Option value.
	 */
	public static function set_option( string $name, mixed $value ): void {
		self::$options[ self::$current_site_id ][ $name ] = $value;
	}

	/**
	 * Configure one option on an explicit site.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $name    Option name.
	 * @param mixed  $value   Option value.
	 */
	public static function set_site_option( int $site_id, string $name, mixed $value ): void {
		self::$options[ $site_id ][ $name ] = $value;
	}

	/**
	 * Determine whether an explicit option exists.
	 *
	 * @param string $name Option name.
	 */
	public static function has_option( string $name ): bool {
		return self::site_has_option( self::$current_site_id, $name );
	}

	/**
	 * Determine whether one option exists on an explicit site.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $name    Option name.
	 */
	public static function site_has_option( int $site_id, string $name ): bool {
		return array_key_exists( $name, self::$options[ $site_id ] ?? array() );
	}

	/**
	 * Read one explicit option.
	 *
	 * @param string $name Option name.
	 */
	public static function option( string $name ): mixed {
		return self::$options[ self::$current_site_id ][ $name ] ?? null;
	}

	/**
	 * Delete one explicit option.
	 *
	 * @param string $name Option name.
	 */
	public static function delete_option( string $name ): void {
		unset( self::$options[ self::$current_site_id ][ $name ] );
	}

	/**
	 * Configure deterministic multisite IDs.
	 *
	 * @param int[] $site_ids Site IDs.
	 */
	public static function configure_multisite( array $site_ids ): void {
		self::$multisite = true;
		self::$site_ids  = array_values( $site_ids );
	}

	/**
	 * Return whether multisite is enabled.
	 */
	public static function is_multisite(): bool {
		return self::$multisite;
	}

	/**
	 * Return a bounded multisite ID batch.
	 *
	 * @param int $offset Starting offset.
	 * @param int $number Maximum number of sites.
	 * @return list<int>
	 */
	public static function site_ids( int $offset, int $number ): array {
		return array_slice( self::$site_ids, $offset, $number );
	}

	/**
	 * Switch deterministic option scope to another site.
	 *
	 * @param int $site_id Site ID.
	 */
	public static function switch_to_site( int $site_id ): void {
		self::$site_stack[]        = self::$current_site_id;
		self::$current_site_id     = $site_id;
		self::$switched_site_ids[] = $site_id;
	}

	/**
	 * Restore the previous deterministic site scope.
	 */
	public static function restore_site(): void {
		self::$current_site_id = array_pop( self::$site_stack ) ?? 1;
	}

	/**
	 * Return site IDs visited through switch_to_blog().
	 *
	 * @return list<int>
	 */
	public static function switched_site_ids(): array {
		return self::$switched_site_ids;
	}

	/**
	 * Return the current deterministic site ID.
	 */
	public static function current_site_id(): int {
		return self::$current_site_id;
	}

	/**
	 * Configure the current singular request.
	 *
	 * @param bool $singular Whether this is an event singular.
	 * @param int  $post_id  Queried event ID.
	 */
	public static function set_singular_event( bool $singular, int $post_id = 0 ): void {
		self::$singular_event    = $singular;
		self::$queried_object_id = $post_id;
	}

	/**
	 * Return the configured singular event decision.
	 */
	public static function is_singular_event(): bool {
		return self::$singular_event;
	}

	/**
	 * Configure the current event archive request.
	 *
	 * @param bool $archive Whether this is the event archive.
	 */
	public static function set_event_archive( bool $archive ): void {
		self::$event_archive = $archive;
	}

	/**
	 * Return the configured event archive decision.
	 */
	public static function is_event_archive(): bool {
		return self::$event_archive;
	}

	/**
	 * Configure an event taxonomy archive request.
	 *
	 * @param string $taxonomy Event taxonomy name, or an empty string.
	 */
	public static function set_event_taxonomy_archive( string $taxonomy ): void {
		self::$event_taxonomy_archive = $taxonomy;
	}

	/**
	 * Return whether the current request matches an event taxonomy.
	 *
	 * @param string|string[] $taxonomies Requested taxonomy names.
	 */
	public static function is_event_taxonomy_archive( string|array $taxonomies ): bool {
		$allowed = is_array( $taxonomies ) ? $taxonomies : array( $taxonomies );

		return in_array( self::$event_taxonomy_archive, $allowed, true );
	}

	/**
	 * Configure the deterministic public archive title.
	 *
	 * @param string $title Public archive title.
	 */
	public static function set_archive_title( string $title ): void {
		self::$archive_title = $title;
	}

	/**
	 * Return the deterministic public archive title.
	 */
	public static function archive_title(): string {
		return self::$archive_title;
	}

	/**
	 * Configure whether the active theme is a full block theme.
	 *
	 * @param bool $block_theme Full block-theme decision.
	 */
	public static function set_block_theme( bool $block_theme ): void {
		self::$block_theme = $block_theme;
	}

	/**
	 * Return whether the active theme is a full block theme.
	 */
	public static function is_block_theme(): bool {
		return self::$block_theme;
	}

	/**
	 * Configure a theme-owned PHP template.
	 *
	 * @param string $template Absolute template path.
	 */
	public static function set_theme_template( string $template ): void {
		self::$theme_template = $template;
	}

	/**
	 * Return the configured theme-owned PHP template.
	 */
	public static function theme_template(): string {
		return self::$theme_template;
	}

	/**
	 * Configure the final full block-theme canvas.
	 *
	 * @param string $template Absolute template path.
	 */
	public static function set_block_template_result( string $template ): void {
		self::$block_template_result = $template;
	}

	/**
	 * Record and resolve a block-template lookup.
	 *
	 * @param string        $template  PHP fallback path.
	 * @param string        $type      Template type.
	 * @param array<string> $templates Template hierarchy.
	 */
	public static function locate_block_template( string $template, string $type, array $templates ): string {
		self::$block_template_calls[] = array(
			'template'  => $template,
			'type'      => $type,
			'templates' => $templates,
		);

		return '' !== self::$block_template_result ? self::$block_template_result : $template;
	}

	/**
	 * Return recorded block-template lookups.
	 *
	 * @return list<array{template: string, type: string, templates: list<string>}>
	 */
	public static function block_template_calls(): array {
		return self::$block_template_calls;
	}

	/**
	 * Record one dynamic block registration.
	 *
	 * @param string $block_type Block name.
	 */
	public static function register_block_type( string $block_type ): void {
		self::$registered_block_types[] = $block_type;
	}

	/**
	 * Return registered dynamic block names.
	 *
	 * @return list<string>
	 */
	public static function registered_block_types(): array {
		return self::$registered_block_types;
	}

	/**
	 * Record one plugin block-template registration.
	 *
	 * @param string $template_name Namespaced template name.
	 */
	public static function register_block_template( string $template_name ): void {
		self::$registered_block_templates[] = $template_name;
	}

	/**
	 * Return registered plugin block-template names.
	 *
	 * @return list<string>
	 */
	public static function registered_block_templates(): array {
		return self::$registered_block_templates;
	}

	/**
	 * Configure deterministic Elementor Theme Builder output.
	 *
	 * @param string $location Core theme location.
	 * @param string $output Captured builder output.
	 */
	public static function set_elementor_location( string $location, string $output ): void {
		self::$elementor_locations[ $location ] = $output;
	}

	/**
	 * Return configured Elementor output, or null when no template handles it.
	 *
	 * @param string $location Core theme location.
	 */
	public static function elementor_location( string $location ): ?string {
		return self::$elementor_locations[ $location ] ?? null;
	}

	/**
	 * Return the configured queried post ID.
	 */
	public static function queried_object_id(): int {
		return self::$queried_object_id;
	}

	/**
	 * Add a role double.
	 *
	 * @param string $role_name WordPress role name.
	 */
	public static function add_role( string $role_name ): FakeRole {
		$role                      = new FakeRole();
		self::$roles[ $role_name ] = $role;

		return $role;
	}

	/**
	 * Retrieve a role double.
	 *
	 * @param string $role_name WordPress role name.
	 */
	public static function role( string $role_name ): ?FakeRole {
		return self::$roles[ $role_name ] ?? null;
	}

	/**
	 * Configure the current capability result.
	 *
	 * @param bool $allowed Authorization result.
	 */
	public static function allow_current_user( bool $allowed ): void {
		self::$current_user_can = $allowed;
	}

	/**
	 * Return the configured capability result.
	 */
	public static function current_user_can(): bool {
		return self::$current_user_can;
	}

	/**
	 * Store one post metadata value.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Metadata key.
	 * @param mixed  $value    Metadata value.
	 */
	public static function update_post_meta( int $post_id, string $meta_key, mixed $value ): void {
		self::$post_meta[ $post_id ][ $meta_key ] = $value;
	}

	/**
	 * Delete one post metadata value.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Metadata key.
	 * @param mixed  $meta_value Optional exact value to remove.
	 */
	public static function delete_post_meta( int $post_id, string $meta_key, mixed $meta_value = null ): bool {
		if ( null !== $meta_value && self::post_meta( $post_id, $meta_key ) !== $meta_value ) {
			return false;
		}

		unset( self::$post_meta[ $post_id ][ $meta_key ] );

		return true;
	}

	/**
	 * Read one post metadata value.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Metadata key.
	 */
	public static function post_meta( int $post_id, string $meta_key ): mixed {
		return self::$post_meta[ $post_id ][ $meta_key ] ?? '';
	}

	/**
	 * Determine whether a metadata value exists.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Metadata key.
	 */
	public static function has_post_meta( int $post_id, string $meta_key ): bool {
		return array_key_exists( $meta_key, self::$post_meta[ $post_id ] ?? array() );
	}

	/**
	 * Store one term metadata value.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Metadata key.
	 * @param mixed  $value    Metadata value.
	 */
	public static function update_term_meta( int $term_id, string $meta_key, mixed $value ): void {
		self::$term_meta[ $term_id ][ $meta_key ] = $value;
	}

	/**
	 * Delete one term metadata value.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Metadata key.
	 */
	public static function delete_term_meta( int $term_id, string $meta_key ): bool {
		unset( self::$term_meta[ $term_id ][ $meta_key ] );

		return true;
	}

	/**
	 * Read one term metadata value.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Metadata key.
	 */
	public static function term_meta( int $term_id, string $meta_key ): mixed {
		return self::$term_meta[ $term_id ][ $meta_key ] ?? '';
	}

	/**
	 * Determine whether one term metadata value exists.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Metadata key.
	 */
	public static function has_term_meta( int $term_id, string $meta_key ): bool {
		return array_key_exists( $meta_key, self::$term_meta[ $term_id ] ?? array() );
	}
}
