<?php
/**
 * Tests for the plugin composition root.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Plugin;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass( Plugin::class )]
/**
 * Tests the plugin composition root contract.
 */
final class PluginTest extends TestCase {
	/**
	 * Reset recorded hooks before every test.
	 */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/**
	 * The composition root cannot be extended with hidden boot behaviour.
	 */
	public function test_plugin_is_a_final_composition_root(): void {
		$reflection = new ReflectionClass( Plugin::class );

		self::assertTrue( $reflection->isFinal() );
	}

	/**
	 * Registration defers booting until all plugins are loaded.
	 */
	public function test_register_defers_boot_until_plugins_loaded(): void {
		$plugin = new Plugin( new OccurrenceRouteFeature( false ) );

		$plugin->register();

		$callback = HookRecorder::action( 'plugins_loaded' );

		self::assertIsCallable( $callback );
		self::assertFalse( HookRecorder::was_fired( 'wpse_loaded' ) );

		$callback();

		self::assertTrue( HookRecorder::was_fired( 'wpse_loaded' ) );
		self::assertCount( 8, HookRecorder::actions( 'init' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_occurrence_index_migrate' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_occurrence_generation_cleanup' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_occurrence_projection_renewal' ) );
		self::assertIsCallable( HookRecorder::action( 'before_delete_post' ) );
		self::assertIsCallable( HookRecorder::action( 'deleted_post' ) );
		self::assertIsCallable( HookRecorder::action( 'wp_restore_post_revision' ) );
		self::assertIsCallable( HookRecorder::action( 'enqueue_block_editor_assets' ) );
		self::assertCount( 2, HookRecorder::actions( 'enqueue_block_editor_assets' ) );
		self::assertIsCallable( HookRecorder::action( 'block_categories_all' ) );
		self::assertIsCallable( HookRecorder::action( 'update_option_wpse_archive_slug' ) );
		self::assertIsCallable( HookRecorder::action( 'add_option_wpse_archive_slug' ) );
		self::assertIsCallable( HookRecorder::action( 'add_meta_boxes_wpse_event' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_event_category_add_form_fields' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_event_category_edit_form_fields' ) );
		self::assertIsCallable( HookRecorder::action( 'created_wpse_event_category' ) );
		self::assertIsCallable( HookRecorder::action( 'edited_wpse_event_category' ) );
		self::assertIsCallable( HookRecorder::action( 'manage_edit-wpse_event_category_columns' ) );
		self::assertIsCallable( HookRecorder::action( 'manage_wpse_event_category_custom_column' ) );
		self::assertIsCallable( HookRecorder::action( 'manage_wpse_event_posts_columns' ) );
		self::assertIsCallable( HookRecorder::action( 'manage_wpse_event_posts_custom_column' ) );
		self::assertIsCallable( HookRecorder::action( 'post_row_actions' ) );
		self::assertIsCallable( HookRecorder::action( 'admin_action_wpse_duplicate_event' ) );
		self::assertIsCallable( HookRecorder::action( 'admin_post_wpse_repair_event_capabilities' ) );
		self::assertIsCallable( HookRecorder::action( 'admin_post_wpse_reindex_event_dates' ) );
		self::assertIsCallable( HookRecorder::action( 'save_post_wpse_event' ) );
		self::assertIsCallable( HookRecorder::action( 'admin_menu' ) );
		self::assertIsCallable( HookRecorder::action( 'admin_init' ) );
		self::assertIsCallable( HookRecorder::action( 'wp_insert_post_data' ) );
		self::assertIsCallable( HookRecorder::action( 'rest_pre_insert_wpse_event' ) );
		self::assertIsCallable( HookRecorder::action( 'rest_prepare_wpse_event' ) );
		self::assertIsCallable( HookRecorder::action( 'rest_after_insert_wpse_event' ) );
		self::assertIsCallable( HookRecorder::action( 'shortcode_wpse_events' ) );
		self::assertIsCallable( HookRecorder::action( 'shortcode_wpse_event_details' ) );
		self::assertIsCallable( HookRecorder::action( 'shortcode_wpse_add_to_calendar' ) );
		self::assertIsCallable( HookRecorder::action( 'shortcode_wpse_calendar' ) );
		self::assertIsCallable( HookRecorder::action( 'pre_get_posts' ) );
		self::assertIsCallable( HookRecorder::action( 'posts_pre_query' ) );
		self::assertIsCallable( HookRecorder::action( 'wp_enqueue_scripts' ) );
		self::assertIsCallable( HookRecorder::action( 'rest_api_init' ) );
		self::assertCount( 3, HookRecorder::actions( 'rest_api_init' ) );
		self::assertCount( 2, HookRecorder::actions( 'query_vars' ) );
		self::assertIsCallable( HookRecorder::action( 'template_redirect' ) );
		self::assertIsCallable( HookRecorder::action( 'template_include' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_render_single_template' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_render_archive_template' ) );
		self::assertIsCallable( HookRecorder::action( 'elementor/loaded' ) );
		self::assertIsCallable( HookRecorder::action( 'divi_module_library_modules_dependency_tree' ) );
		self::assertIsCallable( HookRecorder::action( 'divi_visual_builder_assets_before_enqueue_styles' ) );
		self::assertIsCallable( HookRecorder::action( 'divi_visual_builder_assets_before_enqueue_scripts' ) );
		self::assertIsCallable( HookRecorder::action( 'wp_head' ) );
		self::assertNull( HookRecorder::action( 'elementor/widgets/register' ) );
		self::assertNull( HookRecorder::action( 'wp' ) );
		self::assertNull( HookRecorder::action( 'redirect_canonical' ) );
		self::assertNull( HookRecorder::action( 'wpseo_canonical' ) );
		self::assertNull( HookRecorder::action( 'rank_math/frontend/canonical' ) );
		self::assertNull( HookRecorder::action( 'aioseo_canonical_url' ) );
		self::assertNull( WordPressState::sitemap_provider( 'occurrences' ) );
	}

	/** Public occurrence routes register through the production default. */
	public function test_public_occurrence_route_feature_registers_its_hooks_by_default(): void {
		$plugin = new Plugin();

		$plugin->register();
		$callback = HookRecorder::action( 'plugins_loaded' );

		self::assertIsCallable( $callback );
		$callback();

		self::assertCount( 10, HookRecorder::actions( 'init' ) );
		self::assertCount( 3, HookRecorder::actions( 'query_vars' ) );
		self::assertIsCallable( HookRecorder::action( 'wp' ) );
		self::assertCount( 2, HookRecorder::actions( 'wp' ) );
		self::assertIsCallable( HookRecorder::action( 'redirect_canonical' ) );
		self::assertIsCallable( HookRecorder::action( 'document_title_parts' ) );
		self::assertIsCallable( HookRecorder::action( 'get_canonical_url' ) );
		self::assertCount( 4, HookRecorder::actions( 'rest_api_init' ) );
		self::assertIsCallable( HookRecorder::action( 'wpseo_canonical' ) );
		self::assertIsCallable( HookRecorder::action( 'rank_math/frontend/canonical' ) );
		self::assertIsCallable( HookRecorder::action( 'aioseo_canonical_url' ) );

		$init_callbacks = HookRecorder::actions( 'init' );
		$init_callbacks[9]();
		self::assertInstanceOf(
			\MiMe\WPSimpleEvents\Seo\OccurrenceSitemapProvider::class,
			WordPressState::sitemap_provider( 'occurrences' )
		);
	}
}
