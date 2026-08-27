<?php
/**
 * Conditional Divi 5 integration bootstrapping.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

/**
 * Connects Divi-specific services only to a qualified Divi 5 host.
 */
final readonly class DiviIntegration {
	/**
	 * Create the optional Divi integration boundary.
	 *
	 * @param DiviPostTypeIntegration $post_types Supported post-type adapter.
	 * @param DiviModuleRegistrar     $modules    Native Divi module adapter.
	 * @param DiviPreviewController   $previews   Authenticated live previews.
	 */
	public function __construct(
		private DiviPostTypeIntegration $post_types,
		private DiviModuleRegistrar $modules,
		private DiviPreviewController $previews
	) {}

	/**
	 * Register the dormant WordPress filter used by Divi when it is present.
	 *
	 * Module services stay feature-gated inside their native Divi callbacks.
	 */
	public function register(): void {
		$this->post_types->register();
		$this->modules->register();
		$this->previews->register();
	}
}
