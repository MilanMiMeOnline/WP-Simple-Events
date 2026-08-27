<?php
/**
 * Native Divi 5 module registration boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

/** Registers native modules and editor assets only on a qualified Divi host. */
final readonly class DiviModuleRegistrar {
	private const PACKAGE_NAME = 'wpse-divi-modules';

	/**
	 * Create the feature-gated module registrar.
	 *
	 * @param DiviHost                    $host        Detected optional Divi host.
	 * @param EventTitleModuleRenderer    $title       Host-neutral title renderer.
	 * @param EventFieldModuleRenderer    $fields      Host-neutral atomic field renderer.
	 * @param DiviCompositeModuleRenderer $composites Host-neutral composite renderer.
	 * @param DiviEditorDataProvider      $editor_data Permission-safe editor data.
	 */
	public function __construct(
		private DiviHost $host,
		private EventTitleModuleRenderer $title,
		private EventFieldModuleRenderer $fields,
		private DiviCompositeModuleRenderer $composites,
		private DiviEditorDataProvider $editor_data
	) {}

	/** Register dormant official Divi hooks. */
	public function register(): void {
		add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'add_modules' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_styles', array( $this, 'register_editor_assets' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'register_editor_assets' ) );
	}

	/**
	 * Add module dependencies after Divi has loaded its dependency API.
	 *
	 * @param object $dependency_tree Divi dependency tree instance.
	 */
	public function add_modules( object $dependency_tree ): void {
		if ( ! $this->supported()
			|| ! method_exists( $dependency_tree, 'add_dependency' )
			|| ! interface_exists( 'ET\\Builder\\Framework\\DependencyManagement\\Interfaces\\DependencyInterface' )
			|| ! class_exists( 'ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration' )
		) {
			return;
		}

		$dependency_tree->add_dependency( new EventTitleModule( $this->title ) );

		foreach ( EventFieldModuleRenderer::MODULES as $module_folder => $field ) {
			$dependency_tree->add_dependency( new EventFieldModule( $module_folder, $field, $this->fields ) );
		}

		foreach ( DiviCompositeModuleRenderer::MODULES as $module_folder => $component ) {
			$dependency_tree->add_dependency( new DiviCompositeModule( $module_folder, $component, $this->composites ) );
		}
	}

	/** Register the Visual Builder package in its app window only. */
	public function register_editor_assets(): void {
		if ( ! $this->supported()
			|| ! function_exists( 'et_builder_d5_enabled' )
			|| ! function_exists( 'et_core_is_fb_enabled' )
			|| ! et_builder_d5_enabled()
			|| ! et_core_is_fb_enabled()
			|| ! class_exists( 'ET\\Builder\\VisualBuilder\\Assets\\PackageBuildManager' )
			|| ! defined( 'WPSE_PLUGIN_FILE' )
			|| ! defined( 'WPSE_PLUGIN_DIR' )
			|| ! defined( 'WPSE_VERSION' )
		) {
			return;
		}

		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
			array(
				'name'    => self::PACKAGE_NAME,
				'version' => $this->editor_asset_version(),
				'script'  => array(
					'src'                => plugins_url( 'assets/dist/js/divi-editor.min.js', (string) constant( 'WPSE_PLUGIN_FILE' ) ),
					'deps'               => array( 'divi-module-library', 'divi-vendor-wp-hooks' ),
					'data_app_window'    => $this->editor_data->data(),
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				),
				'style'   => array(
					'src'                => plugins_url( 'assets/src/css/frontend.css', (string) constant( 'WPSE_PLUGIN_FILE' ) ),
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				),
			)
		);
	}

	/** Confirm the exact supported Divi line before touching its classes. */
	private function supported(): bool {
		return $this->host->is_loaded() && DiviCompatibility::supports( $this->host->version() );
	}

	/** Use the bundle content to prevent a stale Visual Builder after updates. */
	private function editor_asset_version(): string {
		$plugin_version = (string) constant( 'WPSE_VERSION' );
		$asset_paths    = array(
			(string) constant( 'WPSE_PLUGIN_DIR' ) . '/assets/dist/js/divi-editor.min.js',
			(string) constant( 'WPSE_PLUGIN_DIR' ) . '/assets/dist/js/calendar.min.js',
			(string) constant( 'WPSE_PLUGIN_DIR' ) . '/assets/src/css/frontend.css',
		);

		if ( array_filter( $asset_paths, static fn ( string $path ): bool => ! is_readable( $path ) ) ) {
			return $plugin_version;
		}

		$asset_hashes = '';

		foreach ( $asset_paths as $asset_path ) {
			$asset_hash = hash_file( 'sha256', $asset_path );

			if ( false === $asset_hash ) {
				return $plugin_version;
			}

			$asset_hashes .= $asset_hash;
		}

		$hash = hash( 'sha256', $asset_hashes );

		return $plugin_version . '-' . substr( $hash, 0, 12 );
	}
}
