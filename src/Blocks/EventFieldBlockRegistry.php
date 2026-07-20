<?php
/**
 * Atomic Gutenberg event-field registration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Query\PublicEventOptions;

/** Registers metadata blocks, the editor adapter and an opt-in event pattern. */
final readonly class EventFieldBlockRegistry {
	public const EDITOR_SCRIPT_HANDLE = 'wpse-event-fields-editor';

	/**
	 * Create the registry with request-shared services.
	 *
	 * @param EventFieldBlockRenderer $renderer Shared block renderer.
	 * @param PublicEventOptions      $events   Bounded public editor choices.
	 * @param EventFieldBlockPattern  $pattern  Opt-in layout pattern.
	 * @param FrontendAssets          $assets   Shared frontend assets.
	 */
	public function __construct(
		private EventFieldBlockRenderer $renderer = new EventFieldBlockRenderer(),
		private PublicEventOptions $events = new PublicEventOptions(),
		private EventFieldBlockPattern $pattern = new EventFieldBlockPattern(),
		private FrontendAssets $assets = new FrontendAssets()
	) {}

	/** Register WordPress hooks without loading editor data on public requests. */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'block_categories_all', array( $this, 'register_category' ) );
	}

	/** Register metadata blocks, shared handles and the opt-in pattern. */
	public function register(): void {
		$this->assets->register_style();
		wp_register_script(
			self::EDITOR_SCRIPT_HANDLE,
			plugin_dir_url( WPSE_PLUGIN_FILE ) . 'assets/dist/js/event-fields-editor.min.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			WPSE_VERSION,
			true
		);

		foreach ( EventFieldBlockDefinitions::slugs() as $slug ) {
			register_block_type(
				WPSE_PLUGIN_DIR . '/blocks/' . $slug,
				array( 'render_callback' => array( $this->renderer, 'render' ) )
			);
		}

		register_block_pattern_category(
			EventFieldBlockDefinitions::CATEGORY,
			array( 'label' => __( 'Simple Events by MiMe', 'simple-events-by-mime' ) )
		);
		register_block_pattern(
			'simple-events-by-mime/single-event-fields',
			array(
				'title'       => __( 'Single Event Fields', 'simple-events-by-mime' ),
				'description' => __( 'A complete flexible event layout built from individual Simple Events by MiMe blocks.', 'simple-events-by-mime' ),
				'categories'  => array( EventFieldBlockDefinitions::CATEGORY ),
				'blockTypes'  => array( 'wpse/event-title' ),
				'content'     => $this->pattern->content(),
			)
		);
	}

	/** Supply the bounded selector only on block-editor screens. */
	public function enqueue_editor_assets(): void {
		wp_localize_script(
			self::EDITOR_SCRIPT_HANDLE,
			'wpseEventFieldBlocks',
			array(
				'events'        => $this->events->options(),
				'eventPostType' => EventPostType::POST_TYPE,
			)
		);
		wp_set_script_translations( self::EDITOR_SCRIPT_HANDLE, 'simple-events-by-mime', WPSE_PLUGIN_DIR . '/languages' );
		wp_enqueue_script( self::EDITOR_SCRIPT_HANDLE );
	}

	/**
	 * Add one discoverable inserter category without disturbing host categories.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing categories.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_category( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( EventFieldBlockDefinitions::CATEGORY === ( $category['slug'] ?? null ) ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => EventFieldBlockDefinitions::CATEGORY,
			'title' => __( 'Simple Events by MiMe', 'simple-events-by-mime' ),
		);

		return $categories;
	}
}
