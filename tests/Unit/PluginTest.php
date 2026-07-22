<?php
/**
 * Tests for the plugin composition root.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Plugin;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
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
		$plugin = new Plugin();

		$plugin->register();

		$callback = HookRecorder::action( 'plugins_loaded' );

		self::assertIsCallable( $callback );
		self::assertFalse( HookRecorder::was_fired( 'wpse_loaded' ) );

		$callback();

		self::assertTrue( HookRecorder::was_fired( 'wpse_loaded' ) );
		self::assertCount( 5, HookRecorder::actions( 'init' ) );
		self::assertIsCallable( HookRecorder::action( 'enqueue_block_editor_assets' ) );
		self::assertIsCallable( HookRecorder::action( 'block_categories_all' ) );
		self::assertIsCallable( HookRecorder::action( 'update_option_wpse_archive_slug' ) );
		self::assertIsCallable( HookRecorder::action( 'add_option_wpse_archive_slug' ) );
		self::assertIsCallable( HookRecorder::action( 'add_meta_boxes_wpse_event' ) );
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
		self::assertIsCallable( HookRecorder::action( 'shortcode_wpse_calendar' ) );
		self::assertIsCallable( HookRecorder::action( 'pre_get_posts' ) );
		self::assertIsCallable( HookRecorder::action( 'wp_enqueue_scripts' ) );
		self::assertIsCallable( HookRecorder::action( 'rest_api_init' ) );
		self::assertIsCallable( HookRecorder::action( 'template_include' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_render_single_template' ) );
		self::assertIsCallable( HookRecorder::action( 'wpse_render_archive_template' ) );
		self::assertIsCallable( HookRecorder::action( 'elementor/loaded' ) );
		self::assertIsCallable( HookRecorder::action( 'wp_head' ) );
		self::assertNull( HookRecorder::action( 'elementor/widgets/register' ) );
	}
}
