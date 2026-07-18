<?php
/**
 * Reusable public event rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use WP_Post;

/**
 * Renders semantic event cards without relying on the global WordPress loop.
 */
final readonly class EventRenderer {
	/**
	 * Create the renderer.
	 *
	 * @param EventDateFormatter $date_formatter Public event date formatter.
	 */
	public function __construct( private EventDateFormatter $date_formatter = new EventDateFormatter() ) {}

	/**
	 * Render one event card with late contextual escaping.
	 *
	 * @param WP_Post          $event   Public event post.
	 * @param EventCardOptions $options Optional section choices.
	 */
	public function card( WP_Post $event, EventCardOptions $options ): string {
		$presentation = $this->date_formatter->format(
			$this->integer_meta( $event->ID, EventMeta::START_UTC ),
			$this->integer_meta( $event->ID, EventMeta::END_UTC ),
			$this->boolean_meta( $event->ID, EventMeta::ALL_DAY ),
			$this->string_meta( $event->ID, EventMeta::TIMEZONE )
		);

		if ( null === $presentation ) {
			return '';
		}

		$title        = trim( get_the_title( $event ) );
		$permalink    = get_permalink( $event );
		$status       = EventStatus::tryFrom( $this->string_meta( $event->ID, EventMeta::STATUS ) );
		$status_label = $this->status_label( $status );
		$status_value = null !== $status ? $status->value : '';
		$venue        = $this->string_meta( $event->ID, EventMeta::VENUE );
		$address      = $this->string_meta( $event->ID, EventMeta::ADDRESS );
		$location     = '' !== $venue ? $venue : $address;
		$location_url = $this->string_meta( $event->ID, EventMeta::LOCATION_URL );
		$title_id     = 'wpse-event-' . $event->ID . '-title';
		$classes      = array( 'wpse-event-card' );
		$excerpt      = $options->show_excerpt ? trim( wp_trim_words( get_the_excerpt( $event ), 30 ) ) : '';

		if ( '' === $title ) {
			$title = __( 'Untitled event', 'wp-simple-events' );
		}

		if ( null !== $status && EventStatus::SCHEDULED !== $status ) {
			$classes[] = 'wpse-event-card-status-' . $status->value;
		}

		ob_start();
		?>
		<article class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<?php if ( $options->show_image && has_post_thumbnail( $event ) ) : ?>
				<a class="wpse-event-card-image-link" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo wp_kses_post( get_the_post_thumbnail( $event, 'medium_large', array( 'class' => 'wpse-event-card-image' ) ) ); ?>
				</a>
			<?php endif; ?>

			<div class="wpse-event-card-body">
				<div class="wpse-event-card-date">
					<time datetime="<?php echo esc_attr( $presentation->start_iso ); ?>" data-wpse-end="<?php echo esc_attr( $presentation->end_iso ); ?>">
						<?php echo esc_html( $presentation->label ); ?>
					</time>
				</div>

				<?php if ( '' !== $status_label ) : ?>
					<p class="wpse-event-status wpse-event-status-<?php echo esc_attr( $status_value ); ?>"><?php echo esc_html( $status_label ); ?></p>
				<?php endif; ?>

				<h3 class="wpse-event-card-title" id="<?php echo esc_attr( $title_id ); ?>">
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
				</h3>

				<?php if ( $options->show_location && '' !== $location ) : ?>
					<p class="wpse-event-card-location">
						<span class="screen-reader-text"><?php esc_html_e( 'Location:', 'wp-simple-events' ); ?></span>
						<?php if ( '' !== $location_url ) : ?>
							<a href="<?php echo esc_url( $location_url ); ?>"><?php echo esc_html( $location ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $location ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<?php if ( '' !== $excerpt ) : ?>
					<p class="wpse-event-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
			</div>
		</article>
		<?php
		$output = ob_get_clean();

		return false === $output ? '' : $output;
	}

	/**
	 * Return the public label for exceptional event statuses.
	 *
	 * @param EventStatus|null $status Validated event status.
	 */
	private function status_label( ?EventStatus $status ): string {
		return match ( $status ) {
			EventStatus::CANCELLED => __( 'Cancelled', 'wp-simple-events' ),
			EventStatus::POSTPONED => __( 'Postponed', 'wp-simple-events' ),
			default => '',
		};
	}

	/**
	 * Read one scalar metadata value as a string.
	 *
	 * @param int    $post_id  Event post ID.
	 * @param string $meta_key Registered meta key.
	 */
	private function string_meta( int $post_id, string $meta_key ): string {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_scalar( $value ) ? (string) $value : '';
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
