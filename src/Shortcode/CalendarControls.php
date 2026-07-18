<?php
/**
 * Calendar filter controls.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use WP_Term;

/**
 * Renders accessible category and tag filters for one calendar instance.
 */
final readonly class CalendarControls {
	/**
	 * Render one progressively enhanced GET filter form.
	 *
	 * @param CalendarShortcodeAttributes $attributes Current normalized filters.
	 * @param string                      $prefix     Instance request prefix.
	 * @param string                      $canvas_id  Controlled calendar canvas ID.
	 * @param array<string, mixed>        $request    Current public request values.
	 */
	public function render(
		CalendarShortcodeAttributes $attributes,
		string $prefix,
		string $canvas_id,
		array $request
	): string {
		$categories = $this->terms( EventTaxonomies::CATEGORY );
		$tags       = $this->terms( EventTaxonomies::TAG );
		$action     = get_permalink( get_queried_object_id() );
		$action     = is_string( $action ) ? $action : '';
		$selected   = array() !== $attributes->category_slugs || array() !== $attributes->tag_slugs;
		$preserved  = $this->preserved_instance_values( $request, $prefix );
		$reset_url  = add_query_arg( $preserved, $action );

		ob_start();
		?>
		<form class="wpse-events-filters wpse-calendar-filters" method="get" action="<?php echo esc_url( $action ); ?>" aria-label="<?php esc_attr_e( 'Filter calendar', 'wp-simple-events' ); ?>" data-wpse-calendar-filters>
			<?php echo $this->preserved_instance_fields( $preserved ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The method escapes every hidden value and attribute. ?>

			<?php if ( array() !== $categories ) : ?>
				<?php $this->term_select( $categories, $prefix . '_category', $prefix . '-category', __( 'Categories', 'wp-simple-events' ), $attributes->category_slugs, 'category' ); ?>
			<?php endif; ?>

			<?php if ( array() !== $tags ) : ?>
				<?php $this->term_select( $tags, $prefix . '_tag', $prefix . '-tag', __( 'Tags', 'wp-simple-events' ), $attributes->tag_slugs, 'tag' ); ?>
			<?php endif; ?>

			<p class="wpse-events-filter-submit">
				<button type="submit" aria-controls="<?php echo esc_attr( $canvas_id ); ?>"><?php esc_html_e( 'Apply filters', 'wp-simple-events' ); ?></button>
				<?php if ( $selected ) : ?>
					<a href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Reset filters', 'wp-simple-events' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}

	/**
	 * Retrieve public, non-empty event terms.
	 *
	 * @param string $taxonomy Event taxonomy.
	 * @return WP_Term[]
	 */
	private function terms( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 100,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		return is_wp_error( $terms ) ? array() : array_values( $terms );
	}

	/**
	 * Render one multiple term selector.
	 *
	 * @param WP_Term[] $terms       Available terms.
	 * @param string    $name        Request field name.
	 * @param string    $id          Input ID.
	 * @param string    $label       Translated label.
	 * @param string[]  $selected    Selected slugs.
	 * @param string    $filter_type Stable JavaScript filter type.
	 */
	private function term_select(
		array $terms,
		string $name,
		string $id,
		string $label,
		array $selected,
		string $filter_type
	): void {
		?>
		<p class="wpse-events-filter-field">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<span class="wpse-events-filter-help" id="<?php echo esc_attr( $id . '-help' ); ?>"><?php esc_html_e( 'Select one or more.', 'wp-simple-events' ); ?></span>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>[]" multiple size="<?php echo esc_attr( (string) min( 4, max( 2, count( $terms ) ) ) ); ?>" aria-describedby="<?php echo esc_attr( $id . '-help' ); ?>" data-wpse-calendar-filter="<?php echo esc_attr( $filter_type ); ?>">
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( in_array( $term->slug, $selected, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Render allowlisted state belonging to other calendar instances.
	 *
	 * @param array<string, string[]> $values Normalized values by request key.
	 */
	private function preserved_instance_fields( array $values ): string {
		$fields = array();

		foreach ( $values as $key => $items ) {
			foreach ( $items as $item ) {
				$fields[] = sprintf(
					'<input type="hidden" name="%1$s[]" value="%2$s">',
					esc_attr( $key ),
					esc_attr( $item )
				);
			}
		}

		return implode( '', $fields );
	}

	/**
	 * Normalize allowlisted state belonging to other calendar instances.
	 *
	 * @param array<string, mixed> $request Current public request values.
	 * @param string               $prefix  Current instance prefix.
	 * @return array<string, string[]>
	 */
	private function preserved_instance_values( array $request, string $prefix ): array {
		$preserved = array();
		$count     = 0;

		foreach ( $request as $key => $value ) {
			if ( $count >= 40
				|| str_starts_with( $key, $prefix . '_' )
				|| 1 !== preg_match( '/^wpse_calendar_\d+_(?:category|tag)$/D', $key ) ) {
				continue;
			}

			$items = is_array( $value ) ? array_slice( $value, 0, 20 ) : array( $value );

			foreach ( $items as $item ) {
				if ( ! is_scalar( $item ) || $count >= 40 ) {
					continue;
				}

				$preserved[ $key ][] = substr( sanitize_text_field( (string) $item ), 0, 200 );
				++$count;
			}
		}

		return $preserved;
	}
}
