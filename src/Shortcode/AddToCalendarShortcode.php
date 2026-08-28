<?php
/**
 * Atomic add-to-calendar shortcode adapter.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarOptions;
use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;

/** Selects current or explicit one-off context and delegates shared rendering. */
final readonly class AddToCalendarShortcode implements ShortcodeRenderer {
	/**
	 * Create the shortcode adapter.
	 *
	 * @param AddToCalendarRenderer $renderer Shared semantic renderer.
	 * @param FrontendAssets        $assets   Scoped frontend assets.
	 */
	public function __construct(
		private AddToCalendarRenderer $renderer = new AddToCalendarRenderer(),
		private FrontendAssets $assets = new FrontendAssets()
	) {}

	/** Register the public shortcode. */
	public function register(): void {
		add_shortcode( 'wpse_add_to_calendar', array( $this, 'render' ) );
	}

	/**
	 * Render current context or one explicit public one-off event.
	 *
	 * @param array<string, mixed>|string $attributes Raw shortcode attributes.
	 */
	public function render( array|string $attributes = array() ): string {
		$attributes  = is_array( $attributes ) ? $attributes : array();
		$options     = AddToCalendarOptions::from_input(
			$attributes['providers'] ?? null,
			$attributes['layout'] ?? null,
			$attributes['label'] ?? null
		);
		$explicit_id = array_key_exists( 'id', $attributes );
		$event_id    = $explicit_id ? $this->event_id( $attributes['id'] ) : get_queried_object_id();

		if ( $event_id <= 0 ) {
			return '';
		}

		$output = $explicit_id
			? $this->renderer->render_public( $event_id, $options )
			: $this->renderer->render_current( $event_id, $options );

		if ( '' !== $output ) {
			$this->assets->enqueue();
		}

		return $output;
	}

	/**
	 * Parse one strict positive explicit event ID.
	 *
	 * @param mixed $value Untrusted shortcode value.
	 */
	private function event_id( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9]\d*$/D', trim( $value ) ) ) {
			return 0;
		}

		$event_id = filter_var( trim( $value ), FILTER_VALIDATE_INT );

		return false !== $event_id && $event_id > 0 ? $event_id : 0;
	}
}
