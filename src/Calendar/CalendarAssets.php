<?php
/**
 * Calendar front-end assets.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Calendar;

use MiMe\WPSimpleEvents\Frontend\FrontendAssets;

/**
 * Registers the locally bundled calendar enhancement only when required.
 */
final readonly class CalendarAssets {
	public const SCRIPT_HANDLE = 'wpse-calendar';

	/**
	 * Create the calendar asset service.
	 *
	 * @param FrontendAssets $frontend Shared component stylesheet.
	 */
	public function __construct( private FrontendAssets $frontend = new FrontendAssets() ) {}

	/**
	 * Register the local calendar bundle for later on-demand enqueueing.
	 */
	public function register(): void {
		wp_register_script(
			self::SCRIPT_HANDLE,
			plugin_dir_url( WPSE_PLUGIN_FILE ) . 'assets/dist/js/calendar.min.js',
			array(),
			WPSE_VERSION,
			true
		);
		wp_script_add_data( self::SCRIPT_HANDLE, 'strategy', 'defer' );
	}

	/**
	 * Enqueue the shared CSS and calendar JavaScript bundle.
	 */
	public function enqueue(): void {
		$this->frontend->enqueue();

		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			$this->register();
		}

		wp_enqueue_script( self::SCRIPT_HANDLE );
	}
}
