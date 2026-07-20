<?php
/**
 * Native event archive controls.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Shortcode\EventListAttributes;
use WP_Term;

/**
 * Renders filters and pagination tied to the native main archive query.
 */
final class EventArchiveControls {
	/**
	 * Render period, category and tag filters.
	 *
	 * @param EventListAttributes $attributes Current normalized archive filters.
	 */
	public function filters( EventListAttributes $attributes ): string {
		$categories = $this->terms( EventTaxonomies::CATEGORY );
		$tags       = $this->terms( EventTaxonomies::TAG );
		$action     = get_post_type_archive_link( EventPostType::POST_TYPE );

		ob_start();
		?>
		<form class="wpse-events-filters wpse-event-archive-filters" method="get" action="<?php echo esc_url( is_string( $action ) ? $action : '' ); ?>" aria-label="<?php esc_attr_e( 'Filter events', 'simple-events-by-mime' ); ?>">
			<p class="wpse-events-filter-field">
				<label for="wpse-archive-period"><?php esc_html_e( 'Period', 'simple-events-by-mime' ); ?></label>
				<select id="wpse-archive-period" name="wpse_period">
					<option value="upcoming" <?php selected( $attributes->period->value, EventPeriod::UPCOMING->value ); ?>><?php esc_html_e( 'Upcoming and active', 'simple-events-by-mime' ); ?></option>
					<option value="past" <?php selected( $attributes->period->value, EventPeriod::PAST->value ); ?>><?php esc_html_e( 'Past', 'simple-events-by-mime' ); ?></option>
					<option value="all" <?php selected( $attributes->period->value, EventPeriod::ALL->value ); ?>><?php esc_html_e( 'All', 'simple-events-by-mime' ); ?></option>
				</select>
			</p>

			<?php if ( array() !== $categories ) : ?>
				<?php $this->term_select( $categories, 'wpse_category', 'wpse-archive-category', __( 'Categories', 'simple-events-by-mime' ), $attributes->category_slugs ); ?>
			<?php endif; ?>

			<?php if ( array() !== $tags ) : ?>
				<?php $this->term_select( $tags, 'wpse_tag', 'wpse-archive-tag', __( 'Tags', 'simple-events-by-mime' ), $attributes->tag_slugs ); ?>
			<?php endif; ?>

			<p class="wpse-events-filter-submit">
				<button type="submit" aria-controls="wpse-archive-results"><?php esc_html_e( 'Apply filters', 'simple-events-by-mime' ); ?></button>
			</p>
		</form>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}

	/**
	 * Render pagination for the native event archive.
	 *
	 * @param int $current_page Current one-based page.
	 * @param int $total_pages  Total archive pages.
	 */
	public function pagination( int $current_page, int $total_pages ): string {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$placeholder = 999999999;
		$page_url    = get_pagenum_link( $placeholder );
		$links       = paginate_links(
			array(
				'base'      => str_replace( (string) $placeholder, '%#%', $page_url ),
				'format'    => '',
				'current'   => max( 1, $current_page ),
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
	 * @param WP_Term[] $terms    Available terms.
	 * @param string    $name     Request field name.
	 * @param string    $id       Input ID.
	 * @param string    $label    Translated label.
	 * @param string[]  $selected Selected slugs.
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
}
