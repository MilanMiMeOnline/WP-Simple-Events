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
	 * Create native archive controls.
	 *
	 * @param EventFilterTermGroup     $term_group Shared semantic taxonomy choices.
	 * @param EventFilterActiveChoices $active_choices Removable active choices.
	 * @param EventFilterDisclosure    $disclosure Progressive panel markup.
	 */
	public function __construct(
		private readonly EventFilterTermGroup $term_group = new EventFilterTermGroup(),
		private readonly EventFilterActiveChoices $active_choices = new EventFilterActiveChoices(),
		private readonly EventFilterDisclosure $disclosure = new EventFilterDisclosure()
	) {}

	/**
	 * Render period, category and tag filters.
	 *
	 * @param EventListAttributes $attributes Current normalized archive filters.
	 */
	public function filters( EventListAttributes $attributes ): string {
		$categories = $this->terms( EventTaxonomies::CATEGORY );
		$tags       = $this->terms( EventTaxonomies::TAG );
		$action     = get_post_type_archive_link( EventPostType::POST_TYPE );
		$action     = is_string( $action ) ? $action : '';
		$current    = array( 'wpse_period' => $attributes->period->value );

		if ( array() !== $attributes->category_slugs ) {
			$current['wpse_category'] = $attributes->category_slugs;
		}

		if ( array() !== $attributes->tag_slugs ) {
			$current['wpse_tag'] = $attributes->tag_slugs;
		}

		$active = $this->active_choices->render(
			new EventFilterViewModel(
				$action,
				array(),
				$current,
				'wpse_category',
				'wpse_tag',
				$categories,
				$tags,
				$attributes->category_slugs,
				$attributes->tag_slugs,
				$action,
				'',
				true
			)
		);

		ob_start();
		?>
			<p class="wpse-events-filter-field">
				<label for="wpse-archive-period"><?php esc_html_e( 'Period', 'mime-simple-events-calendar' ); ?></label>
				<select id="wpse-archive-period" name="wpse_period">
					<option value="upcoming" <?php selected( $attributes->period->value, EventPeriod::UPCOMING->value ); ?>><?php esc_html_e( 'Upcoming and active', 'mime-simple-events-calendar' ); ?></option>
					<option value="past" <?php selected( $attributes->period->value, EventPeriod::PAST->value ); ?>><?php esc_html_e( 'Past', 'mime-simple-events-calendar' ); ?></option>
					<option value="all" <?php selected( $attributes->period->value, EventPeriod::ALL->value ); ?>><?php esc_html_e( 'All', 'mime-simple-events-calendar' ); ?></option>
				</select>
			</p>

			<?php if ( array() !== $categories ) : ?>
				<?php echo $this->term_group->render( $categories, 'wpse_category', 'wpse-archive-category', __( 'Categories', 'mime-simple-events-calendar' ), $attributes->category_slugs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes every value for its output context. ?>
			<?php endif; ?>

			<?php if ( array() !== $tags ) : ?>
				<?php echo $this->term_group->render( $tags, 'wpse_tag', 'wpse-archive-tag', __( 'Tags', 'mime-simple-events-calendar' ), $attributes->tag_slugs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes every value for its output context. ?>
			<?php endif; ?>

			<p class="wpse-events-filter-submit">
				<button type="submit" aria-controls="wpse-archive-results"><?php esc_html_e( 'Apply filters', 'mime-simple-events-calendar' ); ?></button>
				<?php if ( '' === $active ) : ?>
					<a href="<?php echo esc_url( $action ); ?>" data-wpse-filter-clear><?php esc_html_e( 'Clear all', 'mime-simple-events-calendar' ); ?></a>
				<?php endif; ?>
			</p>
		<?php
		$panel = ob_get_clean();
		$panel = false === $panel ? '' : $panel;

		ob_start();
		?>
		<form class="wpse-events-filters wpse-event-archive-filters" method="get" action="<?php echo esc_url( $action ); ?>" aria-label="<?php esc_attr_e( 'Filter events', 'mime-simple-events-calendar' ); ?>" data-wpse-event-filters data-wpse-filter-submitted="1">
			<?php echo $this->disclosure->render( 'wpse-archive-filter-panel', $panel, count( $attributes->category_slugs ) + count( $attributes->tag_slugs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared disclosure and fields own contextual escaping. ?>
		</form>
		<?php
		$output = ob_get_clean();

		return $active . ( false === $output ? '' : $output );
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
				'prev_text' => __( 'Previous', 'mime-simple-events-calendar' ),
				'next_text' => __( 'Next', 'mime-simple-events-calendar' ),
			)
		);

		if ( '' === $links ) {
			return '';
		}

		return '<nav class="wpse-events-pagination" aria-label="'
			. esc_attr__( 'Events pagination', 'mime-simple-events-calendar' )
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
}
