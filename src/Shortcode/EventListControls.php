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
use MiMe\WPSimpleEvents\Frontend\EventFilterActiveChoices;
use MiMe\WPSimpleEvents\Frontend\EventFilterDisclosure;
use MiMe\WPSimpleEvents\Frontend\EventFilterTermGroup;
use MiMe\WPSimpleEvents\Frontend\EventFilterUrlState;
use MiMe\WPSimpleEvents\Frontend\EventFilterViewModel;
use WP_Term;

/**
 * Renders accessible, instance-namespaced public controls.
 */
final class EventListControls {
	/**
	 * Create the public list controls.
	 *
	 * @param EventFilterTermGroup     $term_group Shared semantic taxonomy choices.
	 * @param EventFilterUrlState      $url_state  Bounded cross-instance URL state.
	 * @param EventFilterActiveChoices $active_choices Removable active choices.
	 * @param EventFilterDisclosure    $disclosure Progressive panel markup.
	 */
	public function __construct(
		private readonly EventFilterTermGroup $term_group = new EventFilterTermGroup(),
		private readonly EventFilterUrlState $url_state = new EventFilterUrlState(),
		private readonly EventFilterActiveChoices $active_choices = new EventFilterActiveChoices(),
		private readonly EventFilterDisclosure $disclosure = new EventFilterDisclosure()
	) {}

	/**
	 * Render period, category and tag filters.
	 *
	 * @param EventListAttributes      $attributes Normalized current attributes.
	 * @param string                   $prefix     Stable request prefix.
	 * @param string                   $results_id Controlled results element ID.
	 * @param array<string, mixed>     $request   Current normalized query values.
	 * @param EventListAttributes|null $configured Original component defaults.
	 */
	public function filters(
		EventListAttributes $attributes,
		string $prefix,
		string $results_id,
		array $request,
		?EventListAttributes $configured = null
	): string {
		$configured = $configured ?? $attributes;
		$categories = $this->terms( EventTaxonomies::CATEGORY );
		$tags       = $this->terms( EventTaxonomies::TAG );
		$action     = get_permalink( get_queried_object_id() );
		$action     = is_string( $action ) ? $action : '';
		$submitted  = array_key_exists( $prefix . '_apply', $request )
			|| array_key_exists( $prefix . '_period', $request )
			|| array_key_exists( $prefix . '_category', $request )
			|| array_key_exists( $prefix . '_tag', $request );
		$preserved  = $this->url_state->preserved( $request, $prefix );
		$current    = array(
			$prefix . '_apply'  => '1',
			$prefix . '_period' => $attributes->period->value,
		);

		if ( array() !== $attributes->category_slugs ) {
			$current[ $prefix . '_category' ] = $attributes->category_slugs;
		}

		if ( array() !== $attributes->tag_slugs ) {
			$current[ $prefix . '_tag' ] = $attributes->tag_slugs;
		}

		$clear_url    = $this->url_state->url(
			$action,
			array_merge(
				$preserved,
				array(
					$prefix . '_apply'  => '1',
					$prefix . '_period' => $configured->period->value,
				)
			)
		);
		$has_defaults = array() !== $configured->category_slugs || array() !== $configured->tag_slugs;
		$differs      = $attributes->period !== $configured->period
			|| $attributes->category_slugs !== $configured->category_slugs
			|| $attributes->tag_slugs !== $configured->tag_slugs;
		$restore_url  = $has_defaults && $differs ? $this->url_state->url( $action, $preserved ) : '';
		$active       = $this->active_choices->render(
			new EventFilterViewModel(
				$action,
				$preserved,
				$current,
				$prefix . '_category',
				$prefix . '_tag',
				$categories,
				$tags,
				$attributes->category_slugs,
				$attributes->tag_slugs,
				$clear_url,
				$restore_url,
				$submitted
			)
		);

		ob_start();
		?>
			<p class="wpse-events-filter-field">
				<label for="<?php echo esc_attr( $prefix . '-period' ); ?>"><?php esc_html_e( 'Period', 'mime-simple-events-calendar' ); ?></label>
				<select id="<?php echo esc_attr( $prefix . '-period' ); ?>" name="<?php echo esc_attr( $prefix . '_period' ); ?>">
					<option value="upcoming" <?php selected( $attributes->period->value, EventPeriod::UPCOMING->value ); ?>><?php esc_html_e( 'Upcoming and active', 'mime-simple-events-calendar' ); ?></option>
					<option value="past" <?php selected( $attributes->period->value, EventPeriod::PAST->value ); ?>><?php esc_html_e( 'Past', 'mime-simple-events-calendar' ); ?></option>
					<option value="all" <?php selected( $attributes->period->value, EventPeriod::ALL->value ); ?>><?php esc_html_e( 'All', 'mime-simple-events-calendar' ); ?></option>
				</select>
			</p>

			<?php if ( array() !== $categories ) : ?>
				<?php echo $this->term_group->render( $categories, $prefix . '_category', $prefix . '-category', __( 'Categories', 'mime-simple-events-calendar' ), $attributes->category_slugs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes every value for its output context. ?>
			<?php endif; ?>

			<?php if ( array() !== $tags ) : ?>
				<?php echo $this->term_group->render( $tags, $prefix . '_tag', $prefix . '-tag', __( 'Tags', 'mime-simple-events-calendar' ), $attributes->tag_slugs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes every value for its output context. ?>
			<?php endif; ?>

			<p class="wpse-events-filter-submit">
				<button type="submit" aria-controls="<?php echo esc_attr( $results_id ); ?>"><?php esc_html_e( 'Apply filters', 'mime-simple-events-calendar' ); ?></button>
				<?php if ( '' === $active && $submitted ) : ?>
					<a href="<?php echo esc_url( $clear_url ); ?>" data-wpse-filter-clear><?php esc_html_e( 'Clear all', 'mime-simple-events-calendar' ); ?></a>
					<?php if ( '' !== $restore_url ) : ?>
						<a href="<?php echo esc_url( $restore_url ); ?>" data-wpse-filter-restore><?php esc_html_e( 'Restore defaults', 'mime-simple-events-calendar' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</p>
		<?php
		$panel = ob_get_clean();
		$panel = false === $panel ? '' : $panel;

		ob_start();
		?>
		<form class="wpse-events-filters" method="get" action="<?php echo esc_url( $action ); ?>" aria-label="<?php esc_attr_e( 'Filter events', 'mime-simple-events-calendar' ); ?>" data-wpse-event-filters data-wpse-filter-submitted="<?php echo $submitted ? '1' : '0'; ?>">
			<?php echo $this->url_state->hidden_fields( $preserved ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes every hidden value and attribute. ?>
			<input type="hidden" name="<?php echo esc_attr( $prefix . '_apply' ); ?>" value="1">
			<?php echo $this->disclosure->render( $prefix . '-filter-panel', $panel, count( $attributes->category_slugs ) + count( $attributes->tag_slugs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared disclosure and fields own contextual escaping. ?>
		</form>
		<?php
		$output = ob_get_clean();

		return $active . ( false === $output ? '' : $output );
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
