<?php
/**
 * Event template discovery.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventPostType;

/**
 * Supplies low-priority plugin fallbacks while preserving higher presentation layers.
 */
final readonly class TemplateLoader {
	private const THEME_DIRECTORY = 'simple-events-by-mime/';

	/**
	 * Register template discovery before normal builder filters run.
	 */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'template' ), 0 );
	}

	/**
	 * Select a theme override, block template or bundled PHP fallback.
	 *
	 * @param string $template Previously selected WordPress template.
	 */
	public function template( string $template ): string {
		if ( is_singular( EventPostType::POST_TYPE ) ) {
			return $this->event_template( $template, 'single-wpse_event.php', 'single-wpse_event' );
		}

		if ( is_post_type_archive( EventPostType::POST_TYPE ) ) {
			return $this->event_template( $template, 'archive-wpse_event.php', 'archive-wpse_event' );
		}

		return $template;
	}

	/**
	 * Resolve one fixed event template hierarchy.
	 *
	 * @param string $original Previously selected WordPress template.
	 * @param string $filename Fixed PHP template filename.
	 * @param string $slug     Fixed WordPress template slug.
	 */
	private function event_template( string $original, string $filename, string $slug ): string {
		$theme_template  = locate_template( self::THEME_DIRECTORY . $filename, false, false );
		$plugin_template = WPSE_PLUGIN_DIR . '/templates/' . $filename;
		$candidate       = '' !== $theme_template ? $theme_template : $plugin_template;

		if ( ! is_readable( $candidate ) ) {
			return $original;
		}

		if ( function_exists( 'locate_block_template' ) ) {
			return locate_block_template( $candidate, $slug, array( $slug, 'index' ) );
		}

		return $candidate;
	}
}
