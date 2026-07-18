<?php
/**
 * Block-theme event template registration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventPostType;

/**
 * Registers plugin-owned block templates and their shared dynamic render blocks.
 */
final readonly class BlockTemplates {
	private const TEMPLATE_NAMESPACE = 'wp-simple-events';

	/**
	 * Create the block-template registry.
	 *
	 * @param NativeTemplateRenderer $renderer Shared template controller.
	 */
	public function __construct( private NativeTemplateRenderer $renderer = new NativeTemplateRenderer() ) {}

	/**
	 * Register server-rendered blocks and plugin fallback templates.
	 */
	public function register(): void {
		register_block_type(
			'wpse/native-single',
			array(
				'render_callback' => array( $this->renderer, 'render_single_block' ),
			)
		);
		register_block_type(
			'wpse/native-archive',
			array(
				'render_callback' => array( $this->renderer, 'render_archive_block' ),
			)
		);

		foreach ( $this->definitions() as $slug => $definition ) {
			register_block_template( self::TEMPLATE_NAMESPACE . '//' . $slug, $definition );
		}
	}

	/**
	 * Return the native templates exposed to block themes and the Site Editor.
	 *
	 * @return array<string, array{title: string, description: string, content: string, post_types: string[], plugin: string}>
	 */
	public function definitions(): array {
		return array(
			'single-wpse_event'  => array(
				'title'       => __( 'Single Event', 'wp-simple-events' ),
				'description' => __( 'Native single-event fallback from WP Simple Events.', 'wp-simple-events' ),
				'content'     => '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
					. '<!-- wp:group {"tagName":"main","className":"wpse-template wpse-template-single"} -->'
					. '<main class="wp-block-group wpse-template wpse-template-single"><!-- wp:wpse/native-single /--></main>'
					. '<!-- /wp:group -->'
					. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
				'post_types'  => array( EventPostType::POST_TYPE ),
				'plugin'      => self::TEMPLATE_NAMESPACE,
			),
			'archive-wpse_event' => array(
				'title'       => __( 'Event Archive', 'wp-simple-events' ),
				'description' => __( 'Native event-archive fallback from WP Simple Events.', 'wp-simple-events' ),
				'content'     => '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
					. '<!-- wp:group {"tagName":"main","className":"wpse-template wpse-template-archive"} -->'
					. '<main class="wp-block-group wpse-template wpse-template-archive"><!-- wp:wpse/native-archive /--></main>'
					. '<!-- /wp:group -->'
					. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
				'post_types'  => array( EventPostType::POST_TYPE ),
				'plugin'      => self::TEMPLATE_NAMESPACE,
			),
		);
	}
}
