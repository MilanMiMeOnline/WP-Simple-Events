<?php
/**
 * Conditional Elementor integration bootstrapping.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

/**
 * Loads widget classes only after a compatible Elementor host is available.
 */
final readonly class ElementorIntegration {
	/**
	 * Create the version-gated integration.
	 *
	 * @param WidgetRegistrar $widgets Widget registration service.
	 * @param ElementorHost   $host    Optional host discovery boundary.
	 */
	public function __construct(
		private WidgetRegistrar $widgets,
		private ElementorHost $host = new WordPressElementorHost()
	) {}

	/**
	 * Register against an already loaded host or wait for its public loaded hook.
	 */
	public function register(): void {
		if ( $this->host->is_loaded() ) {
			$this->initialize();
			return;
		}

		add_action( 'elementor/loaded', array( $this, 'initialize' ) );
	}

	/**
	 * Connect official registration hooks only for a supported version.
	 */
	public function initialize(): void {
		if ( ! ElementorCompatibility::supports( $this->host->version() ) ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( $this->widgets, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this->widgets, 'register_widgets' ) );
	}
}
