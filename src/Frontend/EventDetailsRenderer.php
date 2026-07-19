<?php
/**
 * Complete public event-details rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use WP_Post;
use WP_Term;

/**
 * Renders one event from an explicit ID for templates, shortcodes and adapters.
 */
final class EventDetailsRenderer {
	/**
	 * Event IDs currently rendering, used to stop recursive content shortcodes.
	 *
	 * @var array<int, true>
	 */
	private array $rendering = array();

	/**
	 * Create the renderer.
	 *
	 * @param EventDateFormatter $date_formatter Public event date formatter.
	 */
	public function __construct( private readonly EventDateFormatter $date_formatter = new EventDateFormatter() ) {}

	/**
	 * Render a complete event in the required presentation order.
	 *
	 * The caller owns visibility decisions for explicit IDs. Password protection
	 * is always enforced here because every presentation adapter uses this method.
	 *
	 * @param int $event_id Explicit event post ID.
	 */
	public function render( int $event_id ): string {
		$event = get_post( $event_id );

		if ( ! $event instanceof WP_Post || EventPostType::POST_TYPE !== $event->post_type ) {
			return '';
		}

		if ( post_password_required( $event ) ) {
			// WordPress builds and contextually escapes the complete form. Applying
			// wp_kses_post() here would remove the required form and input elements.
			return get_the_password_form( $event );
		}

		if ( isset( $this->rendering[ $event_id ] ) ) {
			return '';
		}

		$this->rendering[ $event_id ] = true;
		$instance                     = RenderInstanceIds::next( RenderInstanceIds::EVENT_DETAILS );

		try {
			return $this->event( $event, 'wpse-event-title-' . $event->ID . '-' . $instance );
		} finally {
			unset( $this->rendering[ $event_id ] );
		}
	}

	/**
	 * Compose one complete event from a validated post object.
	 *
	 * @param WP_Post $event    Event post.
	 * @param string  $title_id Unique heading ID for this rendered instance.
	 */
	private function event( WP_Post $event, string $title_id ): string {
		$title   = trim( get_the_title( $event ) );
		$content = trim( (string) apply_filters( 'the_content', $event->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Rendering stored post content requires the core WordPress content pipeline.

		if ( '' === $title ) {
			$title = __( 'Untitled event', 'wp-simple-events' );
		}

		ob_start();
		?>
		<article class="wpse-single-event" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<header class="wpse-single-event-header">
				<h1 class="wpse-single-event-title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $title ); ?></h1>

				<?php if ( has_post_thumbnail( $event ) ) : ?>
					<div class="wpse-single-event-image">
						<?php echo wp_kses_post( get_the_post_thumbnail( $event, 'large' ) ); ?>
					</div>
				<?php endif; ?>
			</header>

			<?php echo $this->summary( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped by the renderer method. ?>

			<?php if ( '' !== $content ) : ?>
				<div class="wpse-single-event-content">
					<?php echo wp_kses_post( $content ); ?>
				</div>
			<?php endif; ?>

			<?php echo $this->action( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped by the renderer method. ?>
			<?php echo $this->terms( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped by the renderer method. ?>
		</article>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}

	/**
	 * Render date, status and location metadata.
	 *
	 * @param WP_Post $event Event post.
	 */
	private function summary( WP_Post $event ): string {
		$presentation = $this->date_formatter->format(
			$this->integer_meta( $event->ID, EventMeta::START_UTC ),
			$this->integer_meta( $event->ID, EventMeta::END_UTC ),
			$this->boolean_meta( $event->ID, EventMeta::ALL_DAY ),
			$this->string_meta( $event->ID, EventMeta::TIMEZONE )
		);
		$status       = EventStatus::tryFrom( $this->string_meta( $event->ID, EventMeta::STATUS ) );
		$status_label = match ( $status ) {
			EventStatus::CANCELLED => __( 'Cancelled', 'wp-simple-events' ),
			EventStatus::POSTPONED => __( 'Postponed', 'wp-simple-events' ),
			default => '',
		};
		$venue        = $this->string_meta( $event->ID, EventMeta::VENUE );
		$address      = $this->string_meta( $event->ID, EventMeta::ADDRESS );
		$location_url = $this->string_meta( $event->ID, EventMeta::LOCATION_URL );

		if ( null === $presentation && '' === $status_label && '' === $venue && '' === $address && '' === $location_url ) {
			return '';
		}

		ob_start();
		?>
		<div class="wpse-event-summary">
			<?php if ( null !== $presentation ) : ?>
				<p class="wpse-event-date">
					<span class="wpse-event-label"><?php esc_html_e( 'Date and time:', 'wp-simple-events' ); ?></span>
					<time datetime="<?php echo esc_attr( $presentation->start_iso ); ?>" data-wpse-end="<?php echo esc_attr( $presentation->end_iso ); ?>"><?php echo esc_html( $presentation->label ); ?></time>
				</p>
			<?php endif; ?>

			<?php if ( '' !== $status_label && null !== $status ) : ?>
				<p class="wpse-event-status wpse-event-status-<?php echo esc_attr( $status->value ); ?>" role="status"><?php echo esc_html( $status_label ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $venue || '' !== $address || '' !== $location_url ) : ?>
				<div class="wpse-event-location">
					<?php if ( '' !== $venue ) : ?>
						<p class="wpse-event-venue"><span class="wpse-event-label"><?php esc_html_e( 'Location:', 'wp-simple-events' ); ?></span> <?php echo esc_html( $venue ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $address ) : ?>
						<address class="wpse-event-address"><?php echo nl2br( esc_html( $address ) ); ?></address>
					<?php endif; ?>

					<?php if ( '' !== $location_url ) : ?>
						<p class="wpse-event-location-link"><a href="<?php echo esc_url( $location_url ); ?>"><?php esc_html_e( 'View location', 'wp-simple-events' ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}

	/**
	 * Render the optional external event action.
	 *
	 * @param WP_Post $event Event post.
	 */
	private function action( WP_Post $event ): string {
		$url   = $this->string_meta( $event->ID, EventMeta::EVENT_URL );
		$label = $this->string_meta( $event->ID, EventMeta::EVENT_URL_LABEL );

		if ( '' === $url ) {
			return '';
		}

		if ( '' === $label ) {
			$label = __( 'More event information', 'wp-simple-events' );
		}

		return sprintf(
			'<p class="wpse-event-action"><a class="wpse-event-action-link" href="%1$s">%2$s</a></p>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Render linked event categories and tags.
	 *
	 * @param WP_Post $event Event post.
	 */
	private function terms( WP_Post $event ): string {
		$categories = $this->term_links( $event->ID, EventTaxonomies::CATEGORY );
		$tags       = $this->term_links( $event->ID, EventTaxonomies::TAG );

		if ( array() === $categories && array() === $tags ) {
			return '';
		}

		$output = '<footer class="wpse-event-taxonomies">';

		if ( array() !== $categories ) {
			$output .= '<p class="wpse-event-categories"><span class="wpse-event-label">'
				. esc_html__( 'Categories:', 'wp-simple-events' ) . '</span> '
				. implode( '<span aria-hidden="true">, </span>', $categories ) . '</p>';
		}

		if ( array() !== $tags ) {
			$output .= '<p class="wpse-event-tags"><span class="wpse-event-label">'
				. esc_html__( 'Tags:', 'wp-simple-events' ) . '</span> '
				. implode( '<span aria-hidden="true">, </span>', $tags ) . '</p>';
		}

		return $output . '</footer>';
	}

	/**
	 * Build safe links for one event taxonomy.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $taxonomy Event taxonomy.
	 * @return string[]
	 */
	private function term_links( int $event_id, string $taxonomy ): array {
		$terms = get_the_terms( $event_id, $taxonomy );

		if ( false === $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$links = array();

		foreach ( $terms as $term ) {
			$url = get_term_link( $term, $taxonomy );

			if ( is_wp_error( $url ) ) {
				continue;
			}

			$links[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $term->name ) );
		}

		return $links;
	}

	/**
	 * Read one scalar metadata value as a string.
	 *
	 * @param int    $post_id  Event post ID.
	 * @param string $meta_key Registered meta key.
	 */
	private function string_meta( int $post_id, string $meta_key ): string {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Read one numeric metadata value as an integer.
	 *
	 * @param int    $post_id  Event post ID.
	 * @param string $meta_key Registered meta key.
	 */
	private function integer_meta( int $post_id, string $meta_key ): int {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Read one boolean metadata value safely.
	 *
	 * @param int    $post_id  Event post ID.
	 * @param string $meta_key Registered meta key.
	 */
	private function boolean_meta( int $post_id, string $meta_key ): bool {
		$value = get_post_meta( $post_id, $meta_key, true );

		return ( is_bool( $value ) || is_string( $value ) || is_int( $value ) )
			&& rest_sanitize_boolean( $value );
	}
}
