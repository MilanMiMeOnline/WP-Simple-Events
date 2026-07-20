<?php
/**
 * Main plugin composition root.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents;

use MiMe\WPSimpleEvents\Admin\EventMetaBox;
use MiMe\WPSimpleEvents\Admin\EventDuplicateController;
use MiMe\WPSimpleEvents\Admin\EventListTable;
use MiMe\WPSimpleEvents\Admin\EventMaintenanceController;
use MiMe\WPSimpleEvents\Admin\EventSaveController;
use MiMe\WPSimpleEvents\Admin\EventSettingsPage;
use MiMe\WPSimpleEvents\Calendar\CalendarAssets;
use MiMe\WPSimpleEvents\Content\ContentRegistry;
use MiMe\WPSimpleEvents\Elementor\ElementorIntegration;
use MiMe\WPSimpleEvents\Elementor\WidgetRegistrar;
use MiMe\WPSimpleEvents\Frontend\BlockTemplates;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventDetailsRenderer;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Frontend\NativeTemplateRenderer;
use MiMe\WPSimpleEvents\Frontend\TemplateLoader;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Query\EventArchiveQuery;
use MiMe\WPSimpleEvents\Rest\CalendarFeedController;
use MiMe\WPSimpleEvents\Rest\EventRestController;
use MiMe\WPSimpleEvents\Routing\EventArchiveRewriteManager;
use MiMe\WPSimpleEvents\Shortcode\CalendarShortcode;
use MiMe\WPSimpleEvents\Shortcode\EventDetailsShortcode;
use MiMe\WPSimpleEvents\Shortcode\EventListShortcode;
use MiMe\WPSimpleEvents\Seo\StructuredDataController;

/**
 * Registers the plugin's WordPress hooks.
 */
final class Plugin {
	/**
	 * Register hooks needed to boot the plugin.
	 */
	public function register(): void {
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Boot plugin services after all plugins are available.
	 */
	public function boot(): void {
		$content_registry  = new ContentRegistry();
		$installer         = new Installer();
		$event_meta_box    = new EventMetaBox();
		$event_list_table  = new EventListTable();
		$event_maintenance = new EventMaintenanceController();
		$event_duplicates  = new EventDuplicateController();
		$event_saves       = new EventSaveController();
		$event_settings    = new EventSettingsPage();
		$event_rest        = new EventRestController();
		$frontend_assets   = new FrontendAssets();
		$calendar_assets   = new CalendarAssets( $frontend_assets );
		$event_contexts    = new EventContextResolver();
		$event_fields      = new EventFieldRenderer();
		$event_details     = new EventDetailsRenderer( contexts: $event_contexts, fields: $event_fields );
		$event_lists       = new EventListShortcode( assets: $frontend_assets );
		$details_shortcode = new EventDetailsShortcode( $event_details, $frontend_assets );
		$calendar          = new CalendarShortcode( assets: $calendar_assets );
		$elementor         = new ElementorIntegration( new WidgetRegistrar( $event_contexts, $event_fields ) );
		$calendar_feed     = new CalendarFeedController();
		$archive_query     = new EventArchiveQuery();
		$native_templates  = new NativeTemplateRenderer( single: $event_details );
		$block_templates   = new BlockTemplates( $native_templates );
		$template_loader   = new TemplateLoader();
		$structured_data   = new StructuredDataController();
		$archive_rewrites  = new EventArchiveRewriteManager();

		add_action( 'init', array( $content_registry, 'register' ), 5 );
		add_action( 'init', array( $installer, 'maybe_upgrade' ), 6 );
		add_action( 'init', array( $block_templates, 'register' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $calendar_assets, 'register' ) );

		$event_meta_box->register();
		$event_list_table->register();
		$event_maintenance->register();
		$event_duplicates->register();
		$event_saves->register();
		$event_settings->register();
		$event_rest->register();
		$calendar_feed->register();
		$frontend_assets->register();
		$event_lists->register();
		$details_shortcode->register();
		$calendar->register();
		$elementor->register();
		$archive_query->register();
		$native_templates->register();
		$template_loader->register();
		$structured_data->register();
		$archive_rewrites->register();

		/**
		 * Fires after WP Simple Events has booted.
		 *
		 * @since 0.1.0
		 */
		do_action( 'wpse_loaded' );
	}
}
