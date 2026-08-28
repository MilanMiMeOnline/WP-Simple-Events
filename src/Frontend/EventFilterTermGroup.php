<?php
/**
 * Shared public taxonomy filter markup.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Domain\HexColor;
use WP_Term;

/**
 * Renders one accessible, no-JavaScript taxonomy checkbox group.
 */
final readonly class EventFilterTermGroup {
	/**
	 * Render one bounded term choice group.
	 *
	 * @param WP_Term[]          $terms       Available public terms.
	 * @param string             $name        Stable request field name without brackets.
	 * @param string             $id          Unique group ID stem.
	 * @param string             $label       Translated group label.
	 * @param string[]           $selected    Selected term slugs.
	 * @param string             $filter_type Optional stable calendar filter type.
	 * @param array<int, string> $colors Optional normalized category colors.
	 */
	public function render(
		array $terms,
		string $name,
		string $id,
		string $label,
		array $selected,
		string $filter_type = '',
		array $colors = array()
	): string {
		$filter_type = in_array( $filter_type, array( 'category', 'tag' ), true ) ? $filter_type : '';

		ob_start();
		?>
		<fieldset class="wpse-events-filter-group" aria-describedby="<?php echo esc_attr( $id . '-help' ); ?>" data-wpse-filter-group data-wpse-filter-search-label="<?php esc_attr_e( 'Search options', 'mime-simple-events-calendar' ); ?>" data-wpse-filter-search-empty="<?php esc_attr_e( 'No matching options.', 'mime-simple-events-calendar' ); ?>">
			<legend><?php echo esc_html( $label ); ?></legend>
			<span class="wpse-events-filter-help" id="<?php echo esc_attr( $id . '-help' ); ?>"><?php esc_html_e( 'Choose any that apply. Leave all unchecked to include every available option.', 'mime-simple-events-calendar' ); ?></span>
			<div class="wpse-events-filter-options">
				<?php foreach ( array_values( $terms ) as $index => $term ) : ?>
					<?php
					$option_id = $id . '-' . ( $index + 1 );
					$color     = HexColor::normalize( $colors[ $term->term_id ] ?? '' );
					?>
					<label class="wpse-events-filter-option" for="<?php echo esc_attr( $option_id ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( $option_id ); ?>" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $selected, true ) ); ?><?php echo '' !== $filter_type ? ' data-wpse-calendar-filter="' . esc_attr( $filter_type ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute value is escaped and type is allowlisted. ?>>
						<?php if ( '' !== $color ) : ?>
							<span class="wpse-event-category-swatch" aria-hidden="true" style="--wpse-category-color:<?php echo esc_attr( $color ); ?>"></span>
						<?php endif; ?>
						<span><?php echo esc_html( $term->name ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}
}
