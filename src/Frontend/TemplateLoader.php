<?php
/**
 * Event template discovery.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;

/**
 * Supplies low-priority plugin fallbacks while preserving higher presentation layers.
 */
final readonly class TemplateLoader {
	private const THEME_DIRECTORY = 'mime-simple-events-calendar/';

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

		foreach ( array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG ) as $taxonomy ) {
			if ( is_tax( $taxonomy ) ) {
				return $this->event_template(
					$template,
					'archive-wpse_event.php',
					'taxonomy-' . $taxonomy,
					array( 'taxonomy-' . $taxonomy . '.php', 'archive-wpse_event.php' ),
					array( 'taxonomy-' . $taxonomy . '.php', 'archive-wpse_event.php', 'taxonomy.php', 'archive.php', 'index.php' )
				);
			}
		}

		return $template;
	}

	/**
	 * Resolve one fixed event template hierarchy.
	 *
	 * @param string   $original Previously selected WordPress template.
	 * @param string   $filename Fixed PHP template filename.
	 * @param string   $slug             Fixed WordPress template slug.
	 * @param string[] $theme_candidates Optional plugin-directory theme overrides.
	 * @param string[] $block_hierarchy  Optional WordPress block-template hierarchy.
	 */
	private function event_template(
		string $original,
		string $filename,
		string $slug,
		array $theme_candidates = array(),
		array $block_hierarchy = array()
	): string {
		$theme_candidates = array() === $theme_candidates ? array( $filename ) : $theme_candidates;
		$theme_template   = locate_template(
			array_map( static fn ( string $candidate ): string => self::THEME_DIRECTORY . $candidate, $theme_candidates ),
			false,
			false
		);
		$plugin_template  = WPSE_PLUGIN_DIR . '/templates/' . $filename;

		if ( '' !== $theme_template ) {
			return $theme_template;
		}

		if ( ! is_readable( $plugin_template ) ) {
			return $original;
		}

		if ( wp_is_block_theme() ) {
			$block_hierarchy = array() === $block_hierarchy ? array( $filename, 'index.php' ) : $block_hierarchy;

			return locate_block_template( $plugin_template, $slug, $block_hierarchy );
		}

		return $plugin_template;
	}
}
