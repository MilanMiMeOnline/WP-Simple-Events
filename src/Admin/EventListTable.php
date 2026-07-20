<?php
/**
 * Native Events list-table integration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use WP_Query;

/**
 * Adds event-specific columns, filters and sorting to wp-admin.
 */
final class EventListTable {
	/**
	 * Create the list-table adapter.
	 *
	 * @param AdminEventListQuery     $query          Admin query argument builder.
	 * @param AdminEventDateFormatter $date_formatter Compact date formatter.
	 */
	public function __construct(
		private readonly AdminEventListQuery $query = new AdminEventListQuery(),
		private readonly AdminEventDateFormatter $date_formatter = new AdminEventDateFormatter()
	) {}

	/**
	 * Register event-specific admin hooks.
	 */
	public function register(): void {
		add_filter( 'manage_' . EventPostType::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . EventPostType::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . EventPostType::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'apply_query' ), 20 );
	}

	/**
	 * Replace generic post columns with the required event overview.
	 *
	 * @param array<string, string> $columns Existing list-table columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'                                    => $columns['cb'] ?? '<input type="checkbox">',
			'title'                                 => $columns['title'] ?? __( 'Title', 'simple-events-by-mime' ),
			'wpse_start'                            => __( 'Start', 'simple-events-by-mime' ),
			'wpse_end'                              => __( 'End', 'simple-events-by-mime' ),
			'wpse_all_day'                          => __( 'All day', 'simple-events-by-mime' ),
			'wpse_location'                         => __( 'Location', 'simple-events-by-mime' ),
			'taxonomy-' . EventTaxonomies::CATEGORY => $columns[ 'taxonomy-' . EventTaxonomies::CATEGORY ]
				?? __( 'Event Categories', 'simple-events-by-mime' ),
			'wpse_event_status'                     => __( 'Event status', 'simple-events-by-mime' ),
			'wpse_publication_status'               => __( 'Publication', 'simple-events-by-mime' ),
		);
	}

	/**
	 * Render one event-specific column with contextual escaping.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Event post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'wpse_start' === $column || 'wpse_end' === $column ) {
			$this->render_date( $post_id, 'wpse_start' === $column ? EventMeta::START_UTC : EventMeta::END_UTC );
			return;
		}

		if ( 'wpse_all_day' === $column ) {
			echo $this->boolean_meta( $post_id, EventMeta::ALL_DAY )
				? esc_html__( 'Yes', 'simple-events-by-mime' )
				: esc_html__( 'No', 'simple-events-by-mime' );
			return;
		}

		if ( 'wpse_location' === $column ) {
			$parts = array_filter(
				array(
					$this->string_meta( $post_id, EventMeta::VENUE ),
					$this->string_meta( $post_id, EventMeta::ADDRESS ),
				),
				static fn ( string $value ): bool => '' !== $value
			);
			if ( array() === $parts ) {
				echo $this->empty_value(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The helper returns fixed markup and escaped translated text.
			} else {
				echo implode( '<br>', array_map( 'esc_html', $parts ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part is escaped by core immediately above.
			}
			return;
		}

		if ( 'wpse_event_status' === $column ) {
			$status = EventStatus::tryFrom( $this->string_meta( $post_id, EventMeta::STATUS ) );
			$label  = match ( $status ) {
				EventStatus::SCHEDULED => __( 'Scheduled', 'simple-events-by-mime' ),
				EventStatus::CANCELLED => __( 'Cancelled', 'simple-events-by-mime' ),
				EventStatus::POSTPONED => __( 'Postponed', 'simple-events-by-mime' ),
				default => '—',
			};
			echo esc_html( $label );
			return;
		}

		if ( 'wpse_publication_status' === $column ) {
			$post   = get_post( $post_id );
			$status = null === $post ? null : get_post_status_object( $post->post_status );
			echo esc_html( null !== $status ? $status->label : '—' );
		}
	}

	/**
	 * Register only the numeric start and end sort controls.
	 *
	 * @param array<string, string|array<int, bool|string>> $columns Existing sortable columns.
	 * @return array<string, string|array<int, bool|string>>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['wpse_start'] = 'wpse_start';
		$columns['wpse_end']   = 'wpse_end';

		return $columns;
	}

	/**
	 * Render the allowlisted event-view and native category filters once.
	 *
	 * @param string $post_type Current list-table post type.
	 * @param string $which     Top or bottom controls location.
	 */
	public function render_filters( string $post_type, string $which ): void {
		if ( EventPostType::POST_TYPE !== $post_type || 'top' !== $which ) {
			return;
		}

		$current_view = $this->request_value( 'wpse_admin_view' );
		$current_view = in_array( $current_view, array( 'upcoming', 'past', 'cancelled', 'postponed' ), true )
			? $current_view
			: 'all';
		?>
		<label class="screen-reader-text" for="wpse-admin-view"><?php esc_html_e( 'Filter events by timing or status', 'simple-events-by-mime' ); ?></label>
		<select name="wpse_admin_view" id="wpse-admin-view">
			<option value="all" <?php selected( $current_view, 'all' ); ?>><?php esc_html_e( 'All events', 'simple-events-by-mime' ); ?></option>
			<option value="upcoming" <?php selected( $current_view, 'upcoming' ); ?>><?php esc_html_e( 'Upcoming and active', 'simple-events-by-mime' ); ?></option>
			<option value="past" <?php selected( $current_view, 'past' ); ?>><?php esc_html_e( 'Past events', 'simple-events-by-mime' ); ?></option>
			<option value="cancelled" <?php selected( $current_view, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled events', 'simple-events-by-mime' ); ?></option>
			<option value="postponed" <?php selected( $current_view, 'postponed' ); ?>><?php esc_html_e( 'Postponed events', 'simple-events-by-mime' ); ?></option>
		</select>
		<?php
		wp_dropdown_categories(
			array(
				'taxonomy'          => EventTaxonomies::CATEGORY,
				'name'              => EventTaxonomies::CATEGORY,
				'id'                => 'wpse-event-category-filter',
				'show_option_all'   => __( 'All event categories', 'simple-events-by-mime' ),
				'hide_empty'        => false,
				'hierarchical'      => true,
				'value_field'       => 'slug',
				'selected'          => $this->request_value( EventTaxonomies::CATEGORY ),
				'option_none_value' => '',
			)
		);
	}

	/**
	 * Apply filters only to the main Events list query in wp-admin.
	 *
	 * @param WP_Query $query Current query.
	 */
	public function apply_query( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || EventPostType::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$view    = $this->request_value( 'wpse_admin_view' );
		$orderby = $query->get( 'orderby' );
		$order   = $query->get( 'order' );
		$args    = $this->query->arguments(
			$view,
			is_scalar( $orderby ) ? (string) $orderby : '',
			is_scalar( $order ) ? (string) $order : '',
			time()
		);

		foreach ( $args as $key => $value ) {
			if ( 'meta_query' === $key ) {
				$existing = $query->get( 'meta_query' );

				if ( is_array( $existing ) && array() !== $existing ) {
					$value = array(
						'relation' => 'AND',
						$existing,
						$value,
					);
				}
			}

			$query->set( $key, $value );
		}
	}

	/**
	 * Render one formatted event boundary or an accessible empty marker.
	 *
	 * @param int    $post_id  Event post ID.
	 * @param string $meta_key UTC boundary meta key.
	 */
	private function render_date( int $post_id, string $meta_key ): void {
		$value = get_post_meta( $post_id, $meta_key, true );
		$date  = $this->date_formatter->format(
			is_numeric( $value ) ? (int) $value : 0,
			$this->boolean_meta( $post_id, EventMeta::ALL_DAY ),
			$this->string_meta( $post_id, EventMeta::TIMEZONE )
		);

		echo '' === $date
			? $this->empty_value() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The helper returns fixed markup and escaped translated text.
			: esc_html( $date );
	}

	/**
	 * Return a visible empty marker with an accessible meaning.
	 */
	private function empty_value(): string {
		return '<span aria-hidden="true">—</span><span class="screen-reader-text">'
			. esc_html__( 'Not set', 'simple-events-by-mime' )
			. '</span>';
	}

	/**
	 * Read one scalar metadata value.
	 *
	 * @param int    $post_id  Event post ID.
	 * @param string $meta_key Metadata key.
	 */
	private function string_meta( int $post_id, string $meta_key ): string {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Read one strict stored boolean.
	 *
	 * @param int    $post_id  Event post ID.
	 * @param string $meta_key Metadata key.
	 */
	private function boolean_meta( int $post_id, string $meta_key ): bool {
		$value = get_post_meta( $post_id, $meta_key, true );

		return true === $value || 1 === $value || '1' === $value;
	}

	/**
	 * Read a display-only query value and normalize it to one line.
	 *
	 * @param string $key Query-string key.
	 */
	private function request_value( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display/query controls do not change state and are allowlisted by their consumers.
			return '';
		}

		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display/query controls do not change state and are allowlisted by their consumers.
	}
}
