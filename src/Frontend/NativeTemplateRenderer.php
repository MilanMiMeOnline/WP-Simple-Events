<?php
/**
 * Native template presentation controller.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use WP_Block;
use WP_Query;

/**
 * Bridges thin PHP/block templates to shared renderers and Elementor locations.
 */
final readonly class NativeTemplateRenderer {
	/**
	 * Create the native template controller.
	 *
	 * @param EventDetailsRenderer $single  Complete single-event renderer.
	 * @param EventArchiveRenderer $archive Native archive renderer.
	 */
	public function __construct(
		private EventDetailsRenderer $single = new EventDetailsRenderer(),
		private EventArchiveRenderer $archive = new EventArchiveRenderer()
	) {}

	/**
	 * Register the stable actions consumed by bundled PHP templates.
	 */
	public function register(): void {
		add_action( 'wpse_render_single_template', array( $this, 'output_single' ) );
		add_action( 'wpse_render_archive_template', array( $this, 'output_archive' ) );
	}

	/**
	 * Output the Elementor single location or native fallback.
	 */
	public function output_single(): void {
		echo $this->render_single(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor or the shared escaped renderer owns output safety.
	}

	/**
	 * Output the Elementor archive location or native fallback.
	 */
	public function output_archive(): void {
		echo $this->render_archive(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor or the shared escaped renderer owns output safety.
	}

	/**
	 * Dynamic block callback for the registered single-event block template.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Saved block content.
	 * @param WP_Block|null        $block      Runtime block instance.
	 */
	public function render_single_block( array $attributes = array(), string $content = '', ?WP_Block $block = null ): string {
		unset( $attributes, $content, $block );

		return $this->render_single();
	}

	/**
	 * Dynamic block callback for the registered event-archive block template.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Saved block content.
	 * @param WP_Block|null        $block      Runtime block instance.
	 */
	public function render_archive_block( array $attributes = array(), string $content = '', ?WP_Block $block = null ): string {
		unset( $attributes, $content, $block );

		return $this->render_archive();
	}

	/**
	 * Return the applicable single presentation.
	 */
	private function render_single(): string {
		$elementor = $this->elementor_location( 'single' );

		if ( null !== $elementor ) {
			return $elementor;
		}

		return $this->single->render( get_queried_object_id() );
	}

	/**
	 * Return the applicable archive presentation.
	 */
	private function render_archive(): string {
		$elementor = $this->elementor_location( 'archive' );

		if ( null !== $elementor ) {
			return $elementor;
		}

		global $wp_query;

		return $wp_query instanceof WP_Query ? $this->archive->render( $wp_query ) : '';
	}

	/**
	 * Capture an applicable Elementor Pro Theme Builder location.
	 *
	 * A null result means Elementor is absent or has no matching display
	 * condition, allowing the native renderer to continue.
	 *
	 * @param string $location Elementor core theme location.
	 */
	private function elementor_location( string $location ): ?string {
		$callback = $this->optional_function( 'elementor_theme_do_location' );

		if ( null === $callback ) {
			return null;
		}

		ob_start();
		$handled = (bool) $callback( $location );
		$output  = ob_get_clean();

		if ( ! $handled ) {
			return null;
		}

		return is_string( $output ) ? $output : '';
	}

	/**
	 * Resolve an optional integration function at the runtime boundary.
	 *
	 * Keeping this boundary dynamic allows the core to load without Elementor.
	 *
	 * @param string $function_name Fully qualified global function name.
	 */
	private function optional_function( string $function_name ): ?\Closure {
		return function_exists( $function_name ) ? \Closure::fromCallable( $function_name ) : null;
	}
}
