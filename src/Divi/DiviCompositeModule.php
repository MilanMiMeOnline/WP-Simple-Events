<?php
/**
 * Native Divi 5 adapter shared by composite event modules.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Divi's public block objects use camelCase properties.

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ElementScriptData;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WP_Block;

/** Registers and renders one configured composite through Divi's native shell. */
final readonly class DiviCompositeModule implements DependencyInterface {
	/**
	 * Create one thin native composite adapter.
	 *
	 * @param string                      $module_folder Stable module metadata folder.
	 * @param string                      $component     Internal allowlisted renderer key.
	 * @param DiviCompositeModuleRenderer $renderer      Shared native renderer adapter.
	 */
	public function __construct(
		private string $module_folder,
		private string $component,
		private DiviCompositeModuleRenderer $renderer
	) {}

	/** Register after Divi's dependency tree has loaded. */
	public function load(): void {
		add_action( 'init', array( $this, 'register_module' ) );
	}

	/** Register metadata and the configured server callback. */
	public function register_module(): void {
		ModuleRegistration::register_module(
			WPSE_PLUGIN_DIR . '/divi/modules/' . $this->module_folder,
			array( 'render_callback' => array( $this, 'render_callback' ) )
		);
	}

	/**
	 * Render one composite inside Divi's standard module wrapper.
	 *
	 * @param array<string, mixed> $attrs    Normalized module attributes.
	 * @param string               $content  Parsed child content.
	 * @param WP_Block             $block    Current WordPress block.
	 * @param ModuleElements       $elements Divi element renderer.
	 */
	public function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		unset( $content );
		$output = $this->renderer->render( $this->component, $attrs, get_queried_object_id() );

		if ( '' === $output ) {
			return '';
		}

		$parsed       = $block->parsed_block;
		$parent       = BlockParserStore::get_parent( $parsed['id'], $parsed['storeInstance'] );
		$parent_attrs = $parent->attrs ?? array();

		return Module::render(
			array(
				'orderIndex'          => $parsed['orderIndex'],
				'storeInstance'       => $parsed['storeInstance'],
				'id'                  => $parsed['id'],
				'name'                => $block->block_type->name,
				'moduleCategory'      => $block->block_type->category,
				'attrs'               => $attrs,
				'elements'            => $elements,
				'classnamesFunction'  => array( self::class, 'module_classnames' ),
				'stylesComponent'     => array( self::class, 'module_styles' ),
				'scriptDataComponent' => array( self::class, 'module_script_data' ),
				'parentAttrs'         => $parent_attrs,
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'children'            => array(
					ElementComponents::component(
						array(
							'attrs'         => $attrs['module']['decoration'] ?? array(),
							'id'            => $parsed['id'],
							'orderIndex'    => $parsed['orderIndex'],
							'storeInstance' => $parsed['storeInstance'],
						)
					),
					$output,
				),
			)
		);
	}

	/**
	 * Divi callback retained for parity; composites need no custom classes.
	 *
	 * @param array<string, mixed> $args Divi classnames arguments.
	 */
	public static function module_classnames( array $args ): void {
		unset( $args );
	}

	/**
	 * Register standard wrapper and content styles.
	 *
	 * @param array<string, mixed> $args Divi style arguments.
	 */
	public static function module_styles( array $args ): void {
		$elements = $args['elements'];

		Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => array(
					$elements->style(
						array(
							'attrName'   => 'module',
							'styleProps' => array(
								'disabledOn' => array(
									'disabledModuleVisibility' => $args['settings']['disabledModuleVisibility'] ?? null,
								),
							),
						)
					),
					$elements->style( array( 'attrName' => 'content' ) ),
				),
			)
		);
	}

	/**
	 * Register Divi's standard link and position script data.
	 *
	 * @param array<string, mixed> $args Divi script-data arguments.
	 */
	public static function module_script_data( array $args ): void {
		ElementScriptData::set(
			array(
				'id'            => $args['id'] ?? '',
				'selector'      => $args['selector'] ?? '',
				'attrs'         => array_merge(
					$args['attrs']['module']['decoration'] ?? array(),
					array( 'link' => $args['attrs']['module']['advanced']['link'] ?? array() )
				),
				'storeInstance' => $args['storeInstance'] ?? null,
			)
		);
	}
}
