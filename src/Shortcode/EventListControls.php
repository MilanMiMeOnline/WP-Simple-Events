<?php
/**
 * Event list filter and pagination controls.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use WP_Term;

/**
 * Renders accessible, instance-namespaced public controls.
 */
final class EventListControls {
	/**
	 * Render period, category and tag filters.
	 *
	 * @param EventListAttributes  $attributes Normalized current attributes.
	 * @param string               $prefix     Stable request prefix.
	 * @param string               $results_id Controlled results element ID.
	 * @param array<string, mixed> $request   Current normalized query values.
	 */
	public function filters(
		EventListAttributes $attributes,
		string $prefix,
		string $results_id,
		array $request
	): string {
		$categories = $this->terms( EventTaxonomies::CATEGORY );
		$tags       = $this->terms( EventTaxonomies::TAG );
		$action     = get_permalink( get_queried_object_id() );

		ob_start();
		?>
		<form class="wpse-events-filters" method="get" action="<?php echo esc_url( is_string( $action ) ? $action : '' ); ?>" aria-label="<?php esc_attr_e( 'Filter events', 'simple-events-by-mime' ); ?>">
			<?php echo $this->preserved_instance_fields( $request, $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method escapes every hidden value and attribute. ?>

			<p class="wpse-events-filter-field">
				<label for="<?php echo esc_attr( $prefix . '-period' ); ?>"><?php esc_html_e( 'Period', 'simple-events-by-mime' ); ?></label>
				<select id="<?php echo esc_attr( $prefix . '-period' ); ?>" name="<?php echo esc_attr( $prefix . '_period' ); ?>">
					<option value="upcoming" <?php selected( $attributes->period->value, EventPeriod::UPCOMING->value ); ?>><?php esc_html_e( 'Upcoming and active', 'simple-events-by-mime' ); ?></option>
					<option value="past" <?php selected( $attributes->period->value, EventPeriod::PAST->value ); ?>><?php esc_html_e( 'Past', 'simple-events-by-mime' ); ?></option>
					<option value="all" <?php selected( $attributes->period->value, EventPeriod::ALL->value ); ?>><?php esc_html_e( 'All', 'simple-events-by-mime' ); ?></option>
				</select>
			</p>

			<?php if ( array() !== $categories ) : ?>
				<?php $this->term_select( $categories, $prefix . '_category', $prefix . '-category', __( 'Categories', 'simple-events-by-mime' ), $attributes->category_slugs ); ?>
			<?php endif; ?>

			<?php if ( array() !== $tags ) : ?>
				<?php $this->term_select( $tags, $prefix . '_tag', $prefix . '-tag', __( 'Tags', 'simple-events-by-mime' ), $attributes->tag_slugs ); ?>
			<?php endif; ?>

			<p class="wpse-events-filter-submit">
				<button type="submit" aria-controls="<?php echo esc_attr( $results_id ); ?>"><?php esc_html_e( 'Apply filters', 'simple-events-by-mime' ); ?></button>
			</p>
		</form>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}

	/**
	 * Render isolated pagination for one shortcode instance.
	 *
	 * @param int    $current_page Current one-based page.
	 * @param int    $total_pages  Total result pages.
	 * @param string $page_key     Stable namespaced page parameter.
	 */
	public function pagination( int $current_page, int $total_pages, string $page_key ): string {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$base  = add_query_arg( $page_key, '%#%', remove_query_arg( $page_key ) );
		$base  = str_replace( '%25%23%25', '%#%', $base );
		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'type'      => 'list',
				'prev_text' => __( 'Previous', 'simple-events-by-mime' ),
				'next_text' => __( 'Next', 'simple-events-by-mime' ),
			)
		);

		if ( '' === $links ) {
			return '';
		}

		return '<nav class="wpse-events-pagination" aria-label="'
			. esc_attr__( 'Events pagination', 'simple-events-by-mime' )
			. '">' . wp_kses_post( $links ) . '</nav>';
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

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_values( $terms );
	}

	/**
	 * Render one multiple term selector.
	 *
	 * @param WP_Term[] $terms      Available terms.
	 * @param string    $name       Request field name.
	 * @param string    $id         Input ID.
	 * @param string    $label      Translated label.
	 * @param string[]  $selected   Selected slugs.
	 */
	private function term_select( array $terms, string $name, string $id, string $label, array $selected ): void {
		?>
		<p class="wpse-events-filter-field">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<span class="wpse-events-filter-help" id="<?php echo esc_attr( $id . '-help' ); ?>"><?php esc_html_e( 'Select one or more.', 'simple-events-by-mime' ); ?></span>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>[]" multiple size="<?php echo esc_attr( (string) min( 4, max( 2, count( $terms ) ) ) ); ?>" aria-describedby="<?php echo esc_attr( $id . '-help' ); ?>">
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( in_array( $term->slug, $selected, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Preserve state belonging to other event-list instances.
	 *
	 * @param array<string, mixed> $request Current normalized request values.
	 * @param string               $prefix  Current instance prefix.
	 */
	private function preserved_instance_fields( array $request, string $prefix ): string {
		$fields = array();
		$count  = 0;

		foreach ( $request as $key => $value ) {
			if ( $count >= 50
				|| str_starts_with( $key, $prefix . '_' )
				|| 1 !== preg_match( '/^wpse_\d+_(?:period|category|tag|page)$/D', $key ) ) {
				continue;
			}

			$values = is_array( $value ) ? array_slice( $value, 0, 20 ) : array( $value );

			foreach ( $values as $item ) {
				if ( ! is_scalar( $item ) || $count >= 50 ) {
					continue;
				}

				$field_name = is_array( $value ) ? $key . '[]' : $key;
				$fields[]   = sprintf(
					'<input type="hidden" name="%1$s" value="%2$s">',
					esc_attr( $field_name ),
					esc_attr( substr( sanitize_text_field( (string) $item ), 0, 200 ) )
				);
				++$count;
			}
		}

		return implode( '', $fields );
	}
}
