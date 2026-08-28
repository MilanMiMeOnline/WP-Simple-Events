<?php
/**
 * Progressive event-filter disclosure markup.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Keeps the no-JavaScript panel visible while exposing an inert enhancement hook.
 */
final readonly class EventFilterDisclosure {
	/**
	 * Wrap shared filter fields in one progressively enhanced panel.
	 *
	 * @param string $panel_id    Unique panel ID.
	 * @param string $contents    Escaped filter-field markup from shared renderers.
	 * @param int    $active_count Number of selected taxonomy values.
	 * @param string $label        Escaped-late disclosure label.
	 */
	public function render( string $panel_id, string $contents, int $active_count, string $label = '' ): string {
		$active_count = max( 0, $active_count );
		$label        = '' !== $label ? $label : __( 'Filters', 'mime-simple-events-calendar' );

		ob_start();
		?>
		<button class="wpse-events-filter-toggle" type="button" hidden aria-expanded="true" aria-controls="<?php echo esc_attr( $panel_id ); ?>" data-wpse-filter-toggle>
			<span><?php echo esc_html( $label ); ?></span>
			<span class="wpse-events-filter-count" data-wpse-filter-count<?php echo 0 === $active_count ? ' hidden' : ''; ?>><?php echo esc_html( '(' . $active_count . ')' ); ?></span>
		</button>
		<div id="<?php echo esc_attr( $panel_id ); ?>" class="wpse-events-filter-panel" data-wpse-filter-panel>
			<?php echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Callers pass markup rendered and escaped by plugin-owned field renderers. ?>
		</div>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}
}
