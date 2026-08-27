<?php
/**
 * Calendar filter controls.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\EventFilterActiveChoices;
use MiMe\WPSimpleEvents\Frontend\EventFilterTermGroup;
use MiMe\WPSimpleEvents\Frontend\EventFilterUrlState;
use MiMe\WPSimpleEvents\Frontend\EventFilterViewModel;
use WP_Term;

/**
 * Renders accessible category and tag filters for one calendar instance.
 */
final readonly class CalendarControls {
	/**
	 * Create the calendar controls.
	 *
	 * @param EventFilterTermGroup     $term_group Shared semantic taxonomy choices.
	 * @param EventFilterUrlState      $url_state  Bounded cross-instance URL state.
	 * @param EventFilterActiveChoices $active_choices Removable active choices.
	 */
	public function __construct(
		private EventFilterTermGroup $term_group = new EventFilterTermGroup(),
		private EventFilterUrlState $url_state = new EventFilterUrlState(),
		private EventFilterActiveChoices $active_choices = new EventFilterActiveChoices()
	) {}

	/**
	 * Render one progressively enhanced GET filter form.
	 *
	 * @param CalendarShortcodeAttributes      $attributes Current normalized filters.
	 * @param string                           $prefix     Instance request prefix.
	 * @param string                           $canvas_id  Controlled calendar canvas ID.
	 * @param array<string, mixed>             $request    Current public request values.
	 * @param CalendarShortcodeAttributes|null $configured Original component defaults.
	 */
	public function render(
		CalendarShortcodeAttributes $attributes,
		string $prefix,
		string $canvas_id,
		array $request,
		?CalendarShortcodeAttributes $configured = null
	): string {
		$configured = $configured ?? $attributes;
		$categories = $this->terms( EventTaxonomies::CATEGORY );
		$tags       = $this->terms( EventTaxonomies::TAG );

		if ( array() === $categories && array() === $tags ) {
			return '';
		}

		$action    = get_permalink( get_queried_object_id() );
		$action    = is_string( $action ) ? $action : '';
		$submitted = array_key_exists( $prefix . '_apply', $request )
			|| array_key_exists( $prefix . '_category', $request )
			|| array_key_exists( $prefix . '_tag', $request );
		$preserved = $this->url_state->preserved( $request, $prefix );
		$current   = array( $prefix . '_apply' => '1' );

		if ( array() !== $attributes->category_slugs ) {
			$current[ $prefix . '_category' ] = $attributes->category_slugs;
		}

		if ( array() !== $attributes->tag_slugs ) {
			$current[ $prefix . '_tag' ] = $attributes->tag_slugs;
		}

		$clear_url    = $this->url_state->url(
			$action,
			array_merge( $preserved, array( $prefix . '_apply' => '1' ) )
		);
		$has_defaults = array() !== $configured->category_slugs || array() !== $configured->tag_slugs;
		$differs      = $attributes->category_slugs !== $configured->category_slugs
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
		<form class="wpse-events-filters wpse-calendar-filters" method="get" action="<?php echo esc_url( $action ); ?>" aria-label="<?php esc_attr_e( 'Filter calendar', 'mime-simple-events-calendar' ); ?>" data-wpse-calendar-filters>
			<?php echo $this->url_state->hidden_fields( $preserved ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes every hidden value and attribute. ?>
			<input type="hidden" name="<?php echo esc_attr( $prefix . '_apply' ); ?>" value="1">

			<?php if ( array() !== $categories ) : ?>
				<?php echo $this->term_group->render( $categories, $prefix . '_category', $prefix . '-category', __( 'Categories', 'mime-simple-events-calendar' ), $attributes->category_slugs, 'category' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes every value for its output context. ?>
			<?php endif; ?>

			<?php if ( array() !== $tags ) : ?>
				<?php echo $this->term_group->render( $tags, $prefix . '_tag', $prefix . '-tag', __( 'Tags', 'mime-simple-events-calendar' ), $attributes->tag_slugs, 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes every value for its output context. ?>
			<?php endif; ?>

			<p class="wpse-events-filter-submit">
				<button type="submit" aria-controls="<?php echo esc_attr( $canvas_id ); ?>"><?php esc_html_e( 'Apply filters', 'mime-simple-events-calendar' ); ?></button>
				<?php if ( '' === $active && $submitted ) : ?>
					<a href="<?php echo esc_url( $clear_url ); ?>" data-wpse-filter-clear><?php esc_html_e( 'Clear all', 'mime-simple-events-calendar' ); ?></a>
					<?php if ( '' !== $restore_url ) : ?>
						<a href="<?php echo esc_url( $restore_url ); ?>" data-wpse-filter-restore><?php esc_html_e( 'Restore defaults', 'mime-simple-events-calendar' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</p>
		</form>
		<?php
		$output = ob_get_clean();

		return $active . ( false === $output ? '' : $output );
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
}
