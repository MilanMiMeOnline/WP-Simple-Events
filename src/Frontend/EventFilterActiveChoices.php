<?php
/**
 * Removable active event-filter choices.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use WP_Term;

/**
 * Renders active taxonomy filters as independent, no-JavaScript links.
 */
final readonly class EventFilterActiveChoices {
	/**
	 * Create the active-filter renderer.
	 *
	 * @param EventFilterUrlState $url_state Shared bounded URL builder.
	 */
	public function __construct( private EventFilterUrlState $url_state = new EventFilterUrlState() ) {}

	/**
	 * Render active term chips and their scoped actions.
	 *
	 * @param EventFilterViewModel $view Normalized shared filter state.
	 */
	public function render( EventFilterViewModel $view ): string {
		$choices = array_merge(
			$this->choices( $view->categories, $view->selected_categories, $view->category_key, __( 'Category', 'mime-simple-events-calendar' ), 'category' ),
			$this->choices( $view->tags, $view->selected_tags, $view->tag_key, __( 'Tag', 'mime-simple-events-calendar' ), 'tag' )
		);

		if ( array() === $choices ) {
			return '';
		}

		ob_start();
		?>
		<nav class="wpse-events-active-filters" aria-label="<?php esc_attr_e( 'Active filters', 'mime-simple-events-calendar' ); ?>">
			<span class="wpse-events-active-filters-label"><?php esc_html_e( 'Active filters:', 'mime-simple-events-calendar' ); ?></span>
			<ul class="wpse-events-filter-chips">
				<?php foreach ( $choices as $choice ) : ?>
					<?php
					$values = $view->current;
					$slugs  = is_array( $values[ $choice['key'] ] ?? null ) ? $values[ $choice['key'] ] : array();
					$slugs  = array_values( array_diff( $slugs, array( $choice['slug'] ) ) );

					if ( array() === $slugs ) {
						unset( $values[ $choice['key'] ] );
					} else {
						$values[ $choice['key'] ] = $slugs;
					}

					$url          = $this->url_state->url( $view->action, array_merge( $view->preserved, $values ) );
					$choice_label = sprintf( '%1$s: %2$s', $choice['label'], $choice['name'] );
					?>
					<li>
						<a class="wpse-events-filter-chip" href="<?php echo esc_url( $url ); ?>" data-wpse-filter-remove="<?php echo esc_attr( $choice['type'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: active filter label and value. */ __( 'Remove %s', 'mime-simple-events-calendar' ), $choice_label ) ); ?>">
							<span><?php echo esc_html( $choice_label ); ?></span>
							<span aria-hidden="true">×</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<span class="wpse-events-filter-group-actions">
				<?php if ( array() !== $view->selected_categories ) : ?>
					<a href="<?php echo esc_url( $this->group_clear_url( $view, $view->category_key ) ); ?>" data-wpse-filter-clear-group="category"><?php esc_html_e( 'Clear categories', 'mime-simple-events-calendar' ); ?></a>
				<?php endif; ?>
				<?php if ( array() !== $view->selected_tags ) : ?>
					<a href="<?php echo esc_url( $this->group_clear_url( $view, $view->tag_key ) ); ?>" data-wpse-filter-clear-group="tag"><?php esc_html_e( 'Clear tags', 'mime-simple-events-calendar' ); ?></a>
				<?php endif; ?>
			</span>
			<span class="wpse-events-filter-global-actions">
				<a href="<?php echo esc_url( $view->clear_url ); ?>" data-wpse-filter-clear><?php esc_html_e( 'Clear all', 'mime-simple-events-calendar' ); ?></a>
				<?php if ( '' !== $view->restore_url && $view->restore_url !== $view->clear_url ) : ?>
					<a href="<?php echo esc_url( $view->restore_url ); ?>" data-wpse-filter-restore><?php esc_html_e( 'Restore defaults', 'mime-simple-events-calendar' ); ?></a>
				<?php endif; ?>
			</span>
		</nav>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}

	/**
	 * Build one scoped group-clear URL without touching other component state.
	 *
	 * @param EventFilterViewModel $view Shared filter snapshot.
	 * @param string               $key  Group request key to remove.
	 */
	private function group_clear_url( EventFilterViewModel $view, string $key ): string {
		$values = $view->current;
		unset( $values[ $key ] );

		return $this->url_state->url( $view->action, array_merge( $view->preserved, $values ) );
	}

	/**
	 * Resolve selected, still-public term choices without trusting unknown slugs.
	 *
	 * @param WP_Term[] $terms    Available public terms.
	 * @param string[]  $selected Selected slugs.
	 * @param string    $key      Request key.
	 * @param string    $label    Translated singular group label.
	 * @param string    $type     Stable group type.
	 * @return list<array{key: string, slug: string, name: string, label: string, type: string}>
	 */
	private function choices( array $terms, array $selected, string $key, string $label, string $type ): array {
		$choices = array();

		foreach ( $terms as $term ) {
			if ( ! in_array( $term->slug, $selected, true ) ) {
				continue;
			}

			$name = trim( wp_strip_all_tags( $term->name, true ) );

			if ( '' === $name ) {
				continue;
			}

			$choices[] = array(
				'key'   => $key,
				'slug'  => $term->slug,
				'name'  => $name,
				'label' => $label,
				'type'  => $type,
			);
		}

		return $choices;
	}
}
