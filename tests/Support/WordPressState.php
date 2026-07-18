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
	 * Configured post objects keyed by ID.
	 *
	 * @var array<int, \WP_Post>
	 */
	private static array $posts = array();

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
	 * Stored taxonomy term IDs keyed by post and taxonomy.
	 *
	 * @var array<int, array<string, list<int>>>
	 */
	private static array $post_terms = array();

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
		self::$roles                   = array();
		self::$current_user_can        = false;
		self::$post_meta               = array();
		self::$posts                   = array();
		self::$permalinks              = array();
		self::$image_urls              = array();
		self::$options                 = array();
		self::$multisite               = false;
		self::$site_ids                = array( 1 );
		self::$current_site_id         = 1;
		self::$site_stack              = array();
		self::$switched_site_ids       = array();
		self::$singular_event          = false;
		self::$queried_object_id       = 0;
		self::$inserted_post_data      = array();
		self::$deleted_post_ids        = array();
		self::$post_terms              = array();
		self::$taxonomy_terms          = array();
		self::$deleted_terms           = array();
		self::$fail_term_operations    = false;
		self::$fail_meta_operations    = false;
		self::$rewrite_flushes         = 0;
		self::$registered_post_types   = array();
		self::$unregistered_post_types = array();
		self::$registered_taxonomies   = array();
		self::$unregistered_taxonomies = array();
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
	 */
	public static function add_post( \WP_Post $post, string $permalink = '', string $image_url = '' ): void {
		self::$posts[ $post->ID ]      = $post;
		self::$permalinks[ $post->ID ] = $permalink;
		self::$image_urls[ $post->ID ] = $image_url;
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
	 */
	public static function delete_post_meta( int $post_id, string $meta_key ): void {
		unset( self::$post_meta[ $post_id ][ $meta_key ] );
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
}
