<?php
/**
 * Main plugin composition root.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents;

use MiMe\WPSimpleEvents\Admin\EventMetaBox;
use MiMe\WPSimpleEvents\Admin\EventCategoryColorController;
use MiMe\WPSimpleEvents\Admin\EventDuplicateController;
use MiMe\WPSimpleEvents\Admin\EventListTable;
use MiMe\WPSimpleEvents\Admin\EventMaintenanceController;
use MiMe\WPSimpleEvents\Admin\EventSaveController;
use MiMe\WPSimpleEvents\Admin\EventSettingsPage;
use MiMe\WPSimpleEvents\Admin\RecurrenceEditorAssets;
use MiMe\WPSimpleEvents\Admin\OccurrenceSiteHealthController;
use MiMe\WPSimpleEvents\Application\EventPersistence;
use MiMe\WPSimpleEvents\Blocks\EventFieldBlockRenderer;
use MiMe\WPSimpleEvents\Blocks\EventFieldBlockRegistry;
use MiMe\WPSimpleEvents\Blocks\EventCompositeBlockRenderer;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportController;
use MiMe\WPSimpleEvents\Calendar\CalendarAssets;
use MiMe\WPSimpleEvents\Content\ContentRegistry;
use MiMe\WPSimpleEvents\Divi\DiviIntegration;
use MiMe\WPSimpleEvents\Divi\DiviEditorDataProvider;
use MiMe\WPSimpleEvents\Divi\DiviCompositeModuleRenderer;
use MiMe\WPSimpleEvents\Divi\DiviPreviewController;
use MiMe\WPSimpleEvents\Divi\EventFieldModuleRenderer;
use MiMe\WPSimpleEvents\Divi\DiviModuleRegistrar;
use MiMe\WPSimpleEvents\Divi\DiviPostTypeIntegration;
use MiMe\WPSimpleEvents\Divi\EventTitleModuleRenderer;
use MiMe\WPSimpleEvents\Divi\WordPressDiviHost;
use MiMe\WPSimpleEvents\Elementor\ElementorIntegration;
use MiMe\WPSimpleEvents\Elementor\PreviewEventOptions;
use MiMe\WPSimpleEvents\Elementor\WidgetRegistrar;
use MiMe\WPSimpleEvents\Frontend\BlockTemplates;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventArchiveRenderer;
use MiMe\WPSimpleEvents\Frontend\EventDetailsRenderer;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Frontend\NativeTemplateRenderer;
use MiMe\WPSimpleEvents\Frontend\OccurrenceDocumentController;
use MiMe\WPSimpleEvents\Frontend\OccurrenceCollectionPresenter;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationResolver;
use MiMe\WPSimpleEvents\Frontend\TemplateLoader;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Occurrence\OneOffOccurrenceProjector;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceGenerationCleanupController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceLifecycleController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionRenewalController;
use MiMe\WPSimpleEvents\Occurrence\OccurrencePublicationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadiness;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceRevisionController;
use MiMe\WPSimpleEvents\Query\EventArchiveQuery;
use MiMe\WPSimpleEvents\Query\PublicEventOptions;
use MiMe\WPSimpleEvents\Rest\CalendarFeedController;
use MiMe\WPSimpleEvents\Rest\EventRestController;
use MiMe\WPSimpleEvents\Rest\OccurrenceRestController;
use MiMe\WPSimpleEvents\Rest\RecurrenceEditorController;
use MiMe\WPSimpleEvents\Routing\EventArchiveRewriteManager;
use MiMe\WPSimpleEvents\Routing\OccurrenceCacheController;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use MiMe\WPSimpleEvents\Shortcode\CalendarShortcode;
use MiMe\WPSimpleEvents\Shortcode\EventDetailsShortcode;
use MiMe\WPSimpleEvents\Shortcode\EventListShortcode;
use MiMe\WPSimpleEvents\Seo\StructuredDataController;
use MiMe\WPSimpleEvents\Seo\OccurrenceSitemapProvider;
use MiMe\WPSimpleEvents\Seo\ThirdPartyCanonicalController;

/**
 * Registers the plugin's WordPress hooks.
 */
final class Plugin {
	/**
	 * Create the composition root with an explicit unfinished-route gate.
	 *
	 * @param OccurrenceRouteFeature $occurrence_routes Public occurrence route gate.
	 */
	public function __construct(
		private readonly OccurrenceRouteFeature $occurrence_routes = new OccurrenceRouteFeature()
	) {}

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
		$content_registry         = new ContentRegistry();
		$installer                = new Installer();
		$occurrence_index         = new OccurrenceIndexMigrationController();
		$occurrence_cleanup       = new OccurrenceGenerationCleanupController();
		$occurrence_renewal       = new OccurrenceProjectionRenewalController();
		$occurrence_publication   = new OccurrencePublicationController();
		$occurrence_delete        = new OccurrenceLifecycleController();
		$occurrence_revisions     = new OccurrenceRevisionController();
		$event_meta_box           = new EventMetaBox();
		$event_category_colors    = new EventCategoryColorController();
		$event_list_table         = new EventListTable();
		$event_maintenance        = new EventMaintenanceController();
		$event_duplicates         = new EventDuplicateController();
		$event_persistence        = new EventPersistence( new OneOffOccurrenceProjector() );
		$event_saves              = new EventSaveController( persistence: $event_persistence );
		$event_settings           = new EventSettingsPage();
		$occurrence_site_health   = new OccurrenceSiteHealthController();
		$recurrence_assets        = new RecurrenceEditorAssets();
		$event_rest               = new EventRestController( persistence: $event_persistence );
		$recurrence_editor        = new RecurrenceEditorController();
		$frontend_assets          = new FrontendAssets();
		$calendar_assets          = new CalendarAssets( $frontend_assets );
		$event_contexts           = new EventContextResolver();
		$event_fields             = new EventFieldRenderer();
		$public_events            = new PublicEventOptions();
		$event_details            = new EventDetailsRenderer( contexts: $event_contexts, fields: $event_fields );
		$occurrence_presentations = new OccurrencePresentationResolver();
		$occurrence_reads         = new OccurrenceReadRepository();
		$occurrence_readiness     = new OccurrenceReadiness();
		$occurrence_collections   = new OccurrenceCollectionPresenter(
			events: $event_contexts,
			recurring: $occurrence_presentations
		);
		$occurrence_route         = new OccurrenceRouteController( $occurrence_presentations );
		$occurrence_cache         = new OccurrenceCacheController( $occurrence_route );
		$current_presentations    = new CurrentEventPresentationResolver( $event_contexts, $occurrence_route );
		$event_lists              = new EventListShortcode(
			assets: $frontend_assets,
			occurrences: $occurrence_reads,
			occurrence_presenter: $occurrence_collections,
			occurrence_feature: $this->occurrence_routes,
			occurrence_readiness: $occurrence_readiness
		);
		$details_shortcode        = new EventDetailsShortcode( $event_details, $frontend_assets, $current_presentations );
		$calendar                 = new CalendarShortcode(
			assets: $calendar_assets,
			occurrences: $occurrence_reads,
			occurrence_presenter: $occurrence_collections,
			occurrence_feature: $this->occurrence_routes,
			occurrence_readiness: $occurrence_readiness
		);
		$elementor                = new ElementorIntegration(
			new WidgetRegistrar(
				$event_contexts,
				$event_fields,
				previews: new PreviewEventOptions( $public_events ),
				current: $current_presentations,
				details: $details_shortcode
			)
		);
		$divi_host                = new WordPressDiviHost();
		$divi_composites          = new DiviCompositeModuleRenderer( $details_shortcode, $event_lists, $calendar );
		$divi                     = new DiviIntegration(
			new DiviPostTypeIntegration(),
			new DiviModuleRegistrar(
				$divi_host,
				new EventTitleModuleRenderer( $event_contexts, $current_presentations, $event_fields ),
				new EventFieldModuleRenderer( $event_contexts, $current_presentations, $event_fields ),
				$divi_composites,
				new DiviEditorDataProvider( $public_events, $event_contexts, $current_presentations )
			),
			new DiviPreviewController( $divi_composites )
		);
		$calendar_feed            = new CalendarFeedController(
			occurrences: $occurrence_reads,
			occurrence_presenter: $occurrence_collections,
			occurrence_feature: $this->occurrence_routes,
			occurrence_readiness: $occurrence_readiness
		);
		$calendar_export          = new CalendarExportController();
		$archive_query            = new EventArchiveQuery(
			occurrences: $occurrence_reads,
			occurrence_feature: $this->occurrence_routes,
			occurrence_readiness: $occurrence_readiness
		);
		$native_templates         = new NativeTemplateRenderer(
			single: $event_details,
			archive: new EventArchiveRenderer(
				query: $archive_query,
				occurrence_presenter: $occurrence_collections
			),
			occurrences: $occurrence_route
		);
		$block_templates          = new BlockTemplates( $native_templates );
		$field_blocks             = new EventFieldBlockRegistry(
			renderer: new EventFieldBlockRenderer( $event_contexts, $event_fields, $current_presentations ),
			events: $public_events,
			assets: $frontend_assets,
			composites: new EventCompositeBlockRenderer( $event_lists, $calendar, $event_details, $current_presentations )
		);
		$template_loader          = new TemplateLoader();
		$occurrence_document      = new OccurrenceDocumentController( $occurrence_route );
		$occurrence_rest          = new OccurrenceRestController( $occurrence_presentations, $occurrence_route );
		$structured_data          = new StructuredDataController( occurrences: $occurrence_route );
		$third_party_canonicals   = new ThirdPartyCanonicalController( $occurrence_route );
		$occurrence_sitemap       = new OccurrenceSitemapProvider(
			presentations: $occurrence_presentations,
			routes: $occurrence_route
		);
		$archive_rewrites         = new EventArchiveRewriteManager();

		add_action( 'init', array( $content_registry, 'register' ), 5 );
		add_action( 'init', array( $installer, 'maybe_upgrade' ), 6 );
		add_action( 'init', array( $block_templates, 'register' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $calendar_assets, 'register' ) );
		$occurrence_index->register();
		$occurrence_cleanup->register();
		$occurrence_renewal->register();
		$occurrence_publication->register();
		$occurrence_delete->register();
		$occurrence_revisions->register();

		$event_meta_box->register();
		$event_category_colors->register();
		$event_list_table->register();
		$event_maintenance->register();
		$event_duplicates->register();
		$event_saves->register();
		$event_settings->register();
		$occurrence_site_health->register();
		$recurrence_assets->register();
		$event_rest->register();
		$recurrence_editor->register();
		$calendar_feed->register();
		$calendar_export->register();
		$frontend_assets->register();
		$field_blocks->register_hooks();
		$event_lists->register();
		$details_shortcode->register();
		$calendar->register();
		$elementor->register();
		$divi->register();
		$archive_query->register();
		$native_templates->register();
		$template_loader->register();
		$structured_data->register();
		$archive_rewrites->register();

		if ( $this->occurrence_routes->enabled() ) {
			$occurrence_route->register();
			$occurrence_cache->register();
			$occurrence_document->register();
			$occurrence_rest->register();
			$third_party_canonicals->register();
			$occurrence_sitemap->register();
		}

		/**
		 * Fires after MiMe Simple Events and Calendar has booted.
		 *
		 * @since 0.1.0
		 */
		do_action( 'wpse_loaded' );
	}
}
