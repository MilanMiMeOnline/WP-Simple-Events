<?php
/**
 * Global WordPress function doubles for isolated unit tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record a global WordPress action registration.
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Registered callback.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Accepted arguments.
	 */
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		HookRecorder::add_action( $hook_name, $callback );

		return true;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * Report whether a recorded action has fired.
	 *
	 * @param string $hook_name Hook name.
	 */
	function did_action( string $hook_name ): int { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return HookRecorder::was_fired( $hook_name ) ? 1 : 0;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record a global WordPress filter registration as a callback hook.
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Registered callback.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Accepted arguments.
	 */
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		HookRecorder::add_action( $hook_name, $callback );

		return true;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Record a global WordPress shortcode registration.
	 *
	 * @param string   $tag      Shortcode tag.
	 * @param callable $callback Shortcode callback.
	 */
	function add_shortcode( string $tag, callable $callback ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		HookRecorder::add_action( 'shortcode_' . $tag, $callback );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Return an untranslated test string.
	 *
	 * @param string $text   Source string.
	 * @param string $domain Text domain.
	 */
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Return an escaped translated test string.
	 *
	 * @param string $text   Source string.
	 * @param string $domain Text domain.
	 */
	function esc_html__( string $text, string $domain = 'default' ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		unset( $domain );

		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escape deterministic test HTML text.
	 *
	 * @param string $text Raw text.
	 */
	function esc_html( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escape deterministic test attribute text.
	 *
	 * @param string $text Raw attribute text.
	 */
	function esc_attr( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Echo escaped deterministic test attribute text.
	 *
	 * @param string $text   Source text.
	 * @param string $domain Text domain.
	 */
	function esc_attr_e( string $text, string $domain = 'default' ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		unset( $domain );

		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Echo escaped deterministic test HTML text.
	 *
	 * @param string $text   Source text.
	 * @param string $domain Text domain.
	 */
	function esc_html_e( string $text, string $domain = 'default' ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		unset( $domain );

		echo esc_html( $text );
	}
}

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Return or echo a deterministic selected attribute.
	 *
	 * @param mixed $selected Selected value.
	 * @param mixed $current  Current value.
	 * @param bool  $display  Whether to echo the attribute.
	 */
	function selected( mixed $selected, mixed $current = true, bool $display = true ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$result = $selected === $current ? ' selected="selected"' : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed test-only HTML.
		}

		return $result;
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	/**
	 * Return the deterministic site timezone used by unit tests.
	 */
	function wp_timezone_string(): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$timezone = WordPressState::has_option( 'timezone_string' )
			? WordPressState::option( 'timezone_string' )
			: 'Europe/Brussels';

		if ( is_string( $timezone ) && '' !== $timezone ) {
			return $timezone;
		}

		$offset  = WordPressState::has_option( 'gmt_offset' )
			? WordPressState::option( 'gmt_offset' )
			: 0;
		$offset  = is_numeric( $offset ) ? (float) $offset : 0.0;
		$sign    = $offset < 0 ? '-' : '+';
		$hours   = (int) floor( abs( $offset ) );
		$minutes = (int) round( ( abs( $offset ) - $hours ) * 60 );

		if ( 60 === $minutes ) {
			++$hours;
			$minutes = 0;
		}

		return sprintf( '%s%02d:%02d', $sign, $hours, $minutes );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Return deterministic formatting and pagination options.
	 *
	 * @param string $option_name WordPress option name.
	 * @param mixed  $fallback    Fallback value.
	 */
	function get_option( string $option_name, mixed $fallback = false ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( WordPressState::has_option( $option_name ) ) {
			return WordPressState::option( $option_name );
		}

		return match ( $option_name ) {
			'date_format'    => 'Y-m-d',
			'time_format'    => 'H:i',
			'posts_per_page' => 10,
			default          => $fallback,
		};
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Store a deterministic option value.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 * @param bool   $autoload    Autoload choice.
	 */
	function update_option( string $option_name, mixed $value, bool $autoload = true ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		WordPressState::set_option( $option_name, $value );

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Delete a deterministic option value.
	 *
	 * @param string $option_name Option name.
	 */
	function delete_option( string $option_name ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$exists = WordPressState::has_option( $option_name );
		WordPressState::delete_option( $option_name );

		return $exists;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Retrieve a configured post object.
	 *
	 * @param int $post_id Post ID.
	 */
	function get_post( int $post_id ): ?WP_Post { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return WordPressState::post( $post_id );
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	/**
	 * Return the deterministic post type for an object or identifier.
	 *
	 * @param WP_Post|int|null $post Post object or ID.
	 */
	function get_post_type( WP_Post|int|null $post = null ): string|false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( $post instanceof WP_Post ) {
			return $post->post_type;
		}

		$resolved = is_int( $post ) ? get_post( $post ) : null;

		return $resolved instanceof WP_Post ? $resolved->post_type : false;
	}
}

if ( ! function_exists( 'get_block_wrapper_attributes' ) ) {
	/**
	 * Return deterministic escaped block-support wrapper attributes.
	 *
	 * @param array<string, string> $extra_attributes Explicit wrapper attributes.
	 */
	function get_block_wrapper_attributes( array $extra_attributes = array() ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$attributes = array();

		foreach ( $extra_attributes as $name => $value ) {
			$attributes[] = esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return implode( ' ', $attributes );
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	/**
	 * Keep ordinary isolated save tests outside autosave context.
	 *
	 * @param int $post_id Post ID.
	 */
	function wp_is_post_autosave( int $post_id ): false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		unset( $post_id );

		return false;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	/**
	 * Keep ordinary isolated save tests outside revision context.
	 *
	 * @param int $post_id Post ID.
	 */
	function wp_is_post_revision( int $post_id ): false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		unset( $post_id );

		return false;
	}
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	/**
	 * Retrieve one deterministic root-level page by slug.
	 *
	 * @param string $page_path Requested page path.
	 */
	function get_page_by_path( string $page_path ): ?WP_Post { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return WordPressState::post_by_path( $page_path, 'page' );
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	/**
	 * Record one deterministic rewrite-rule flush.
	 *
	 * @param bool $hard Whether a hard flush was requested.
	 */
	function flush_rewrite_rules( bool $hard = true ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		unset( $hard );
		WordPressState::record_rewrite_flush();
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Keep isolated query tests on the public request path.
	 */
	function is_admin(): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return false;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Return bounded post IDs for uninstall tests.
	 *
	 * @param array<string, mixed> $arguments Query arguments.
	 * @return list<int>
	 */
	function get_posts( array $arguments = array() ): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$post_type = is_string( $arguments['post_type'] ?? null ) ? $arguments['post_type'] : 'post';
		$limit     = is_int( $arguments['posts_per_page'] ?? null ) ? $arguments['posts_per_page'] : 5;
		$page      = is_int( $arguments['paged'] ?? null ) ? max( 1, $arguments['paged'] ) : 1;
		$offset    = is_int( $arguments['offset'] ?? null ) ? max( 0, $arguments['offset'] ) : ( $page - 1 ) * $limit;
		$statuses  = $arguments['post_status'] ?? array();
		$statuses  = is_string( $statuses ) ? array( $statuses ) : ( is_array( $statuses ) ? $statuses : array() );
		$statuses  = array_values( array_filter( $statuses, 'is_string' ) );

		return WordPressState::post_ids( $post_type, $limit, $offset, $statuses );
	}
}

if ( ! function_exists( 'get_post_stati' ) ) {
	/**
	 * Return the deterministic core publication statuses.
	 *
	 * @return array<string, string>
	 */
	function get_post_stati(): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return array(
			'publish'    => 'publish',
			'future'     => 'future',
			'draft'      => 'draft',
			'pending'    => 'pending',
			'private'    => 'private',
			'trash'      => 'trash',
			'auto-draft' => 'auto-draft',
		);
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * Insert one deterministic post.
	 *
	 * @param array<string, mixed> $post_data Submitted post data.
	 * @param bool                 $wp_error  Whether an error object is requested.
	 */
	function wp_insert_post( array $post_data, bool $wp_error = false ): int|WP_Error { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		return WordPressState::insert_post( $post_data );
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	/**
	 * Permanently delete a deterministic post.
	 *
	 * @param int  $post_id      Post ID.
	 * @param bool $force_delete Whether trash should be bypassed.
	 */
	function wp_delete_post( int $post_id, bool $force_delete = false ): ?WP_Post { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		$post = WordPressState::post( $post_id );
		WordPressState::delete_post( $post_id );

		return $post;
	}
}

if ( ! function_exists( 'metadata_exists' ) ) {
	/**
	 * Determine whether deterministic post metadata exists.
	 *
	 * @param string $meta_type Object metadata type.
	 * @param int    $post_id   Post ID.
	 * @param string $meta_key  Metadata key.
	 */
	function metadata_exists( string $meta_type, int $post_id, string $meta_key ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		return WordPressState::has_post_meta( $post_id, $meta_key );
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	/**
	 * Return deterministic post term IDs.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param string               $taxonomy Taxonomy name.
	 * @param array<string, mixed> $args     Query arguments.
	 * @return list<int>|WP_Error
	 */
	function wp_get_post_terms( int $post_id, string $taxonomy, array $args = array() ): array|WP_Error { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		if ( WordPressState::term_operations_fail() ) {
			return new WP_Error( 'term_read_failed', 'Term read failed.' );
		}

		return WordPressState::post_terms( $post_id, $taxonomy );
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	/**
	 * Assign deterministic term IDs to a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param int[]  $term_ids Term IDs.
	 * @param string $taxonomy Taxonomy name.
	 * @param bool   $append   Whether existing terms should remain.
	 * @return list<int>|WP_Error
	 */
	function wp_set_object_terms( int $post_id, array $term_ids, string $taxonomy, bool $append = false ): array|WP_Error { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		if ( WordPressState::term_operations_fail() ) {
			return new WP_Error( 'term_write_failed', 'Term write failed.' );
		}

		WordPressState::set_post_terms( $post_id, $taxonomy, $term_ids );

		return $term_ids;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	/**
	 * Return deterministic standalone term IDs.
	 *
	 * @param array<string, mixed> $arguments Query arguments.
	 * @return list<int>|WP_Error
	 */
	function get_terms( array $arguments = array() ): array|WP_Error { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( WordPressState::term_operations_fail() ) {
			return new WP_Error( 'term_read_failed', 'Term read failed.' );
		}

		$taxonomy = is_string( $arguments['taxonomy'] ?? null ) ? $arguments['taxonomy'] : '';
		$limit    = is_int( $arguments['number'] ?? null ) ? $arguments['number'] : 0;

		return WordPressState::taxonomy_terms( $taxonomy, $limit );
	}
}

if ( ! function_exists( 'wp_delete_term' ) ) {
	/**
	 * Delete one deterministic standalone term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 */
	function wp_delete_term( int $term_id, string $taxonomy ): bool|WP_Error { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( WordPressState::term_operations_fail() ) {
			return new WP_Error( 'term_delete_failed', 'Term deletion failed.' );
		}

		WordPressState::delete_term( $taxonomy, $term_id );

		return true;
	}
}

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * Accept deterministic post-type registration.
	 *
	 * @param string               $post_type Post type key.
	 * @param array<string, mixed> $arguments Registration arguments.
	 */
	function register_post_type( string $post_type, array $arguments = array() ): object { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		WordPressState::register_post_type( $post_type );

		return (object) array( 'name' => $post_type );
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	/**
	 * Determine whether a deterministic post type is registered.
	 *
	 * @param string $post_type Requested post type key.
	 */
	function post_type_exists( string $post_type ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return WordPressState::post_type_exists( $post_type );
	}
}

if ( ! function_exists( 'unregister_post_type' ) ) {
	/**
	 * Unregister one deterministic post type.
	 *
	 * @param string $post_type Post type key.
	 */
	function unregister_post_type( string $post_type ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( ! WordPressState::post_type_exists( $post_type ) ) {
			return false;
		}

		WordPressState::unregister_post_type( $post_type );

		return true;
	}
}

if ( ! function_exists( 'register_taxonomy' ) ) {
	/**
	 * Accept deterministic taxonomy registration.
	 *
	 * @param string               $taxonomy   Taxonomy key.
	 * @param string|string[]      $object_type Object type keys.
	 * @param array<string, mixed> $arguments  Registration arguments.
	 */
	function register_taxonomy( string $taxonomy, string|array $object_type, array $arguments = array() ): object { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		WordPressState::register_taxonomy( $taxonomy );

		return (object) array( 'name' => $taxonomy );
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	/**
	 * Determine whether a deterministic taxonomy is registered.
	 *
	 * @param string $taxonomy Requested taxonomy key.
	 */
	function taxonomy_exists( string $taxonomy ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return WordPressState::taxonomy_exists( $taxonomy );
	}
}

if ( ! function_exists( 'unregister_taxonomy' ) ) {
	/**
	 * Unregister one deterministic taxonomy.
	 *
	 * @param string $taxonomy Taxonomy key.
	 */
	function unregister_taxonomy( string $taxonomy ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( ! WordPressState::taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		WordPressState::unregister_taxonomy( $taxonomy );

		return true;
	}
}

if ( ! function_exists( 'register_taxonomy_for_object_type' ) ) {
	/**
	 * Accept deterministic taxonomy relationship registration.
	 *
	 * @param string $taxonomy   Taxonomy key.
	 * @param string $object_type Object type key.
	 */
	function register_taxonomy_for_object_type( string $taxonomy, string $object_type ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Identify the WordPress error runtime double.
	 *
	 * @param mixed $value Candidate value.
	 */
	function is_wp_error( mixed $value ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Build a deterministic admin URL.
	 *
	 * @param string $path Relative admin path.
	 */
	function admin_url( string $path = '' ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Build a deterministic action nonce.
	 *
	 * @param int|string $action Nonce action.
	 */
	function wp_create_nonce( int|string $action = -1 ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return 'nonce-' . (string) $action;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escape one deterministic URL for HTML output.
	 *
	 * @param string $url Raw URL.
	 */
	function esc_url( string $url ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Retrieve a configured public post URL.
	 *
	 * @param WP_Post|int $post Post or post ID.
	 */
	function get_permalink( WP_Post|int $post ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return WordPressState::permalink( $post instanceof WP_Post ? $post->ID : $post );
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Retrieve a configured post title.
	 *
	 * @param WP_Post|int $post Post or post ID.
	 */
	function get_the_title( WP_Post|int $post ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$post_object = $post instanceof WP_Post ? $post : WordPressState::post( $post );

		return $post_object instanceof WP_Post ? $post_object->post_title : '';
	}
}

if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
	/**
	 * Retrieve a configured public featured-image URL.
	 *
	 * @param WP_Post|int $post Post or post ID.
	 * @param string      $size Requested image size.
	 */
	function get_the_post_thumbnail_url( WP_Post|int $post, string $size = 'post-thumbnail' ): string|false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		$url = WordPressState::image_url( $post instanceof WP_Post ? $post->ID : $post );

		return '' === $url ? false : $url;
	}
}

if ( ! function_exists( 'has_post_thumbnail' ) ) {
	/**
	 * Determine whether a deterministic post has a featured image.
	 *
	 * @param WP_Post|int $post Post or post ID.
	 */
	function has_post_thumbnail( WP_Post|int $post ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return '' !== WordPressState::image_url( $post instanceof WP_Post ? $post->ID : $post );
	}
}

if ( ! function_exists( 'get_the_post_thumbnail' ) ) {
	/**
	 * Build deterministic featured-image markup.
	 *
	 * @param WP_Post|int          $post       Post or post ID.
	 * @param string|int[]         $size       Requested image size.
	 * @param array<string, mixed> $attr       Image attributes.
	 */
	function get_the_post_thumbnail( WP_Post|int $post, string|array $size = 'post-thumbnail', array $attr = array() ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$post_id = $post instanceof WP_Post ? $post->ID : $post;
		$url     = WordPressState::image_url( $post_id );

		if ( '' === $url ) {
			return '';
		}

		$size_name = is_string( $size ) ? $size : 'custom';
		$class     = is_string( $attr['class'] ?? null ) ? $attr['class'] : 'attachment-' . $size_name . ' size-' . $size_name;
		$alt       = is_string( $attr['alt'] ?? null ) ? $attr['alt'] : WordPressState::image_alt( $post_id );

		return sprintf(
			'<img src="%1$s" class="%2$s" alt="%3$s">',
			esc_url( $url ),
			esc_attr( $class ),
			esc_attr( $alt )
		);
	}
}

if ( ! function_exists( 'get_the_excerpt' ) ) {
	/**
	 * Retrieve a deterministic excerpt with a core-like content fallback.
	 *
	 * @param WP_Post|int $post Post or post ID.
	 */
	function get_the_excerpt( WP_Post|int $post ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$post_object = $post instanceof WP_Post ? $post : WordPressState::post( $post );

		if ( ! $post_object instanceof WP_Post ) {
			return '';
		}

		$value = '' !== trim( $post_object->post_excerpt )
			? $post_object->post_excerpt
			: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post_object->post_content ), true ), 55 );

		return (string) WordPressState::apply_filter( 'get_the_excerpt', $value, $post_object );
	}
}

if ( ! function_exists( 'post_password_required' ) ) {
	/**
	 * Treat every configured password as locked in isolated tests.
	 *
	 * @param WP_Post|int|null $post Post, post ID or current post.
	 */
	function post_password_required( WP_Post|int|null $post = null ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$post_object = $post instanceof WP_Post ? $post : ( is_int( $post ) ? WordPressState::post( $post ) : null );

		return $post_object instanceof WP_Post && '' !== $post_object->post_password;
	}
}

if ( ! function_exists( 'get_the_password_form' ) ) {
	/**
	 * Return one deterministic complete password form.
	 *
	 * @param WP_Post|int|null $post Post, post ID or current post.
	 */
	function get_the_password_form( WP_Post|int|null $post = null ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$post_object = $post instanceof WP_Post ? $post : ( is_int( $post ) ? WordPressState::post( $post ) : null );
		$post_id     = $post_object instanceof WP_Post ? $post_object->ID : 0;

		return '<form class="post-password-form" data-post="' . esc_attr( (string) $post_id ) . '"><input type="password"></form>';
	}
}

if ( ! function_exists( 'get_the_terms' ) ) {
	/**
	 * Return deterministic post term objects.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return WP_Term[]|false|WP_Error
	 */
	function get_the_terms( int $post_id, string $taxonomy ): array|false|WP_Error { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( WordPressState::term_operations_fail() ) {
			return new WP_Error( 'term_read_failed', 'Term read failed.' );
		}

		$terms = array();

		foreach ( WordPressState::post_terms( $post_id, $taxonomy ) as $term_id ) {
			$term = WordPressState::term( $term_id );

			if ( $term instanceof WP_Term ) {
				$terms[] = $term;
			}
		}

		return array() === $terms ? false : $terms;
	}
}

if ( ! function_exists( 'get_term_link' ) ) {
	/**
	 * Return one deterministic term URL.
	 *
	 * @param WP_Term|int $term     Term object or ID.
	 * @param string      $taxonomy Taxonomy name.
	 */
	function get_term_link( WP_Term|int $term, string $taxonomy = '' ): string|WP_Error { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		$url = WordPressState::term_link( $term instanceof WP_Term ? $term->term_id : $term );

		return '' === $url ? new WP_Error( 'missing_term_link', 'Term link missing.' ) : $url;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Preserve deterministic trusted test markup.
	 *
	 * @param string $data Markup to filter.
	 */
	function wp_kses_post( string $data ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return $data;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strip HTML tags from test content.
	 *
	 * @param string $text          Raw text.
	 * @param bool   $remove_breaks Whether to collapse line breaks.
	 */
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$text = strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Implements the wp_strip_all_tags() test double itself.

		return $remove_breaks ? ( preg_replace( '/[\r\n\t ]+/', ' ', $text ) ?? '' ) : $text;
	}
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
	/**
	 * Remove shortcode-like segments from deterministic test content.
	 *
	 * @param string $content Raw post content.
	 */
	function strip_shortcodes( string $content ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return preg_replace( '/\[[^\]]+\]/', '', $content ) ?? '';
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	/**
	 * Trim plain text to a deterministic word limit.
	 *
	 * @param string $text      Plain text.
	 * @param int    $num_words Maximum words.
	 * @param string $more      Truncation marker.
	 */
	function wp_trim_words( string $text, int $num_words = 55, string $more = '…' ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$words = preg_split( '/\s+/', trim( $text ) );
		$words = false === $words ? array() : $words;

		return count( $words ) > $num_words
			? implode( ' ', array_slice( $words, 0, $num_words ) ) . $more
			: implode( ' ', $words );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * Return the configured event singular state.
	 *
	 * @param string|string[] $post_types Requested post types.
	 */
	function is_singular( string|array $post_types = '' ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		unset( $post_types );

		return WordPressState::is_singular_event();
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * Return the configured queried post ID.
	 */
	function get_queried_object_id(): int { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return WordPressState::queried_object_id();
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Preserve a filter value in isolated tests.
	 *
	 * @param string $hook_name Filter hook name.
	 * @param mixed  $value     Filtered value.
	 * @param mixed  ...$args   Filter context.
	 */
	function apply_filters( string $hook_name, mixed $value, mixed ...$args ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		return WordPressState::apply_filter( $hook_name, $value, ...$args );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Format a timestamp in an explicit timezone.
	 *
	 * @param string            $format    PHP date format.
	 * @param int|null          $timestamp Unix timestamp.
	 * @param DateTimeZone|null $timezone  Output timezone.
	 */
	function wp_date( string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null ): string|false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$timestamp ??= time();
		$timezone  ??= new DateTimeZone( 'UTC' );

		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Encode JSON using the production flags supplied by the caller.
	 *
	 * @param mixed $value   Value to encode.
	 * @param int   $flags   JSON encoding flags.
	 * @param int   $depth   Maximum nesting depth.
	 */
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return json_encode( $value, $flags, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Implements the wp_json_encode() test double itself.
	}
}

if ( ! function_exists( 'get_role' ) ) {
	/**
	 * Retrieve a configured role double.
	 *
	 * @param string $role_name WordPress role name.
	 */
	function get_role( string $role_name ): ?object { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return WordPressState::role( $role_name );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Read a post metadata test value.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Metadata key.
	 * @param bool   $single   Single-value selection.
	 */
	function get_post_meta( int $post_id, string $meta_key, bool $single = false ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		return WordPressState::post_meta( $post_id, $meta_key );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Store a post metadata test value.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Metadata key.
	 * @param mixed  $value    Metadata value.
	 */
	function update_post_meta( int $post_id, string $meta_key, mixed $value ): int|bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( WordPressState::meta_operations_fail() ) {
			return false;
		}

		WordPressState::update_post_meta( $post_id, $meta_key, $value );

		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * Delete a post metadata test value.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Metadata key.
	 */
	function delete_post_meta( int $post_id, string $meta_key ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( WordPressState::meta_operations_fail() ) {
			return false;
		}

		WordPressState::delete_post_meta( $post_id, $meta_key );

		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Return the configured capability decision.
	 *
	 * @param string $capability Requested capability.
	 * @param mixed  ...$args    Capability context.
	 */
	function current_user_can( string $capability, mixed ...$args ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress test double.
		return WordPressState::current_user_can();
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Sanitize a one-line text value for unit tests.
	 *
	 * @param string $value Raw value.
	 */
	function sanitize_text_field( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$value = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $value ) ?? '';
		$value = preg_replace( '/<[^>]*>/', '', $value ) ?? '';

		return trim( preg_replace( '/[\r\n\t ]+/', ' ', $value ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Normalize a deterministic ASCII term slug.
	 *
	 * @param string $value Raw title.
	 */
	function sanitize_title( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value ) ?? '';

		return trim( $value, '-' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Sanitize multiline text for unit tests.
	 *
	 * @param string $value Raw value.
	 */
	function sanitize_textarea_field( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$value = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $value ) ?? '';

		$value = preg_replace( '/<[^>]*>/', '', $value ) ?? '';

		return trim( preg_replace( '/[\t ]+/', ' ', $value ) ?? '' );
	}
}

if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
	/**
	 * Normalize a WordPress REST boolean for unit tests.
	 *
	 * @param bool|string|int $value Raw value.
	 */
	function rest_sanitize_boolean( bool|string|int $value ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return ! in_array( $value, array( false, '', 'false', 'FALSE', '0', 0 ), true );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Allow only structurally valid URLs with an approved scheme.
	 *
	 * @param string   $url       Raw URL.
	 * @param string[] $protocols Allowed schemes.
	 */
	function esc_url_raw( string $url, array $protocols = array( 'http', 'https' ) ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$allowed_protocol = false;

		foreach ( $protocols as $protocol ) {
			if ( str_starts_with( strtolower( $url ), strtolower( $protocol ) . '://' ) ) {
				$allowed_protocol = true;
				break;
			}
		}

		if ( ! $allowed_protocol ) {
			return '';
		}

		return false === filter_var( $url, FILTER_VALIDATE_URL ) ? '' : $url;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Recursively remove slashes from test request data.
	 *
	 * @param mixed $value Slashed value.
	 * @return mixed
	 */
	function wp_unslash( mixed $value ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Accept the deterministic valid nonce used by controller tests.
	 *
	 * @param string $nonce  Submitted nonce.
	 * @param string $action Expected action.
	 */
	function wp_verify_nonce( string $nonce, string $action ): int|false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return 'valid-event-nonce' === $nonce && 'wpse_save_event' === $action ? 1 : false;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Keep isolated tests in single-site mode.
	 */
	function is_multisite(): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return WordPressState::is_multisite();
	}
}

if ( ! function_exists( 'get_sites' ) ) {
	/**
	 * Return one deterministic multisite ID batch.
	 *
	 * @param array<string, mixed> $arguments Site query arguments.
	 * @return list<int>
	 */
	function get_sites( array $arguments = array() ): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$offset = is_int( $arguments['offset'] ?? null ) ? $arguments['offset'] : 0;
		$number = is_int( $arguments['number'] ?? null ) ? $arguments['number'] : 100;

		return WordPressState::site_ids( $offset, $number );
	}
}

if ( ! function_exists( 'switch_to_blog' ) ) {
	/**
	 * Switch deterministic site scope.
	 *
	 * @param int $site_id Site ID.
	 */
	function switch_to_blog( int $site_id ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		WordPressState::switch_to_site( $site_id );

		return true;
	}
}

if ( ! function_exists( 'restore_current_blog' ) ) {
	/**
	 * Restore deterministic site scope.
	 */
	function restore_current_blog(): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		WordPressState::restore_site();

		return true;
	}
}

if ( ! function_exists( 'ms_is_switched' ) ) {
	/**
	 * Report that tests have not switched sites.
	 */
	function ms_is_switched(): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		return 1 !== WordPressState::current_site_id();
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Add deterministic query arguments in controller tests.
	 *
	 * @param array<string, mixed>|string $key      Query arguments or one query key.
	 * @param mixed                       $value    Existing URL for array input, or query value.
	 * @param string                      $location Existing URL for scalar input.
	 */
	function add_query_arg( array|string $key, mixed $value = '', string $location = '' ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress test double.
		$arguments = is_array( $key ) ? $key : array( $key => $value );
		$url       = is_array( $key ) && is_string( $value ) ? $value : $location;
		$query     = http_build_query( $arguments, '', '&', PHP_QUERY_RFC3986 );

		if ( '' === $query ) {
			return $url;
		}

		$separator = str_contains( $url, '?' ) ? '&' : '?';

		return $url . $separator . $query;
	}
}
