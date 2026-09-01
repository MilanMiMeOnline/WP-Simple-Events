<?php
/**
 * Reusable public event rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Domain\EventColorPresentation;
use MiMe\WPSimpleEvents\Domain\HexColor;
use WP_Post;

/**
 * Renders semantic event cards without relying on the global WordPress loop.
 */
final readonly class EventRenderer {
	/**
	 * Create the renderer.
	 *
	 * @param EventPresentationFactory $presentations Normalized presentation factory.
	 */
	public function __construct( private EventPresentationFactory $presentations = new EventPresentationFactory() ) {}

	/**
	 * Render one event card with late contextual escaping.
	 *
	 * @param WP_Post                     $event   Public event post.
	 * @param EventCardOptions            $options Optional section choices.
	 * @param EventColorPresentation|null $color Optional resolved calendar color.
	 * @param string                      $scope Optional collection scope for unique DOM IDs.
	 */
	public function card(
		WP_Post $event,
		EventCardOptions $options,
		?EventColorPresentation $color = null,
		string $scope = ''
	): string {
		return $this->card_presentation( $this->presentations->create( $event ), $options, '', $color, $scope );
	}

	/**
	 * Render one normalized event or occurrence card with late contextual escaping.
	 *
	 * @param EventPresentation           $presentation Effective public presentation.
	 * @param EventCardOptions            $options      Optional section choices.
	 * @param string                      $identity     Optional occurrence identity for unique DOM IDs.
	 * @param EventColorPresentation|null $color        Optional resolved calendar color.
	 * @param string                      $scope        Optional collection scope for unique DOM IDs.
	 */
	public function card_presentation(
		EventPresentation $presentation,
		EventCardOptions $options,
		string $identity = '',
		?EventColorPresentation $color = null,
		string $scope = ''
	): string {
		if ( null === $presentation->date ) {
			return '';
		}

		$event        = $presentation->event;
		$title        = $presentation->title;
		$permalink    = $presentation->permalink;
		$status       = $presentation->status;
		$status_label = $this->status_label( $status );
		$status_value = null !== $status ? $status->value : '';
		$venue        = $presentation->venue;
		$address      = $presentation->address;
		$location     = '' !== $venue ? $venue : $address;
		$location_url = $presentation->location_url;
		$title_id     = $this->title_id( $event->ID, $identity, $scope );
		$classes      = array( 'wpse-event-card' );
		$style        = '';
		$excerpt      = $options->show_excerpt
			? trim( wp_trim_words( get_the_excerpt( $event ), $options->excerpt_length ) )
			: '';
		$label_attr   = $options->show_title
			? ' aria-labelledby="' . esc_attr( $title_id ) . '"'
			: ' aria-label="' . esc_attr( $title ) . '"';

		if ( null !== $status && EventStatus::SCHEDULED !== $status ) {
			$classes[] = 'wpse-event-card-status-' . $status->value;
		}

		if ( null !== $color ) {
			$background = HexColor::normalize( $color->background );

			if ( '' !== $background ) {
				$classes[] = 'wpse-event-card-has-color';
				$style     = ' style="--wpse-event-color:' . esc_attr( $background ) . '"';
			}
		}

		ob_start();
		?>
		<article class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Strict normalized color and fixed property name. ?><?php echo $label_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The complete attribute is escaped above. ?>>
			<?php if ( $options->show_image && $presentation->featured_image_id > 0 ) : ?>
				<a class="wpse-event-card-image-link" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo wp_kses_post( wp_get_attachment_image( $presentation->featured_image_id, 'medium_large', false, array( 'class' => 'wpse-event-card-image' ) ) ); ?>
				</a>
			<?php endif; ?>

			<div class="wpse-event-card-body">
				<?php if ( $options->show_date ) : ?>
					<div class="wpse-event-card-date">
						<time datetime="<?php echo esc_attr( $presentation->date->start_iso ); ?>" data-wpse-end="<?php echo esc_attr( $presentation->date->end_iso ); ?>">
							<?php echo esc_html( $presentation->date->label ); ?>
						</time>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $status_label ) : ?>
					<p class="wpse-event-status wpse-event-status-<?php echo esc_attr( $status_value ); ?>"><?php echo esc_html( $status_label ); ?></p>
				<?php endif; ?>

				<?php if ( $options->show_title ) : ?>
					<<?php echo esc_attr( $options->heading_level ); ?> class="wpse-event-card-title" id="<?php echo esc_attr( $title_id ); ?>">
						<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
					</<?php echo esc_attr( $options->heading_level ); ?>>
				<?php endif; ?>

				<?php if ( $options->show_location && '' !== $location ) : ?>
					<p class="wpse-event-card-location">
						<span class="screen-reader-text"><?php esc_html_e( 'Location:', 'mime-simple-events-calendar' ); ?></span>
						<?php if ( '' !== $location_url ) : ?>
							<a href="<?php echo esc_url( $location_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $location ); ?></a>
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
	 * Build one stable card heading ID without trusting adapter-provided text.
	 *
	 * @param int    $event_id Canonical event post ID.
	 * @param string $identity Optional occurrence identity.
	 * @param string $scope Optional collection scope.
	 */
	private function title_id( int $event_id, string $identity, string $scope ): string {
		$identity = $this->id_segment( $identity );
		$scope    = $this->id_segment( $scope );
		$suffix   = '' !== $scope ? '-' . $scope : '';
		$suffix  .= '' !== $identity ? '-' . $identity : '';

		return 'wpse-event-' . $event_id . $suffix . '-title';
	}

	/**
	 * Normalize one bounded DOM ID segment without trusting renderer input.
	 *
	 * @param string $value Untrusted adapter-provided segment.
	 */
	private function id_segment( string $value ): string {
		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9_-]/', '', $value );

		return is_string( $value ) ? substr( $value, 0, 64 ) : '';
	}

	/**
	 * Return the public label for exceptional event statuses.
	 *
	 * @param EventStatus|null $status Validated event status.
	 */
	private function status_label( ?EventStatus $status ): string {
		return match ( $status ) {
			EventStatus::CANCELLED => __( 'Cancelled', 'mime-simple-events-calendar' ),
			EventStatus::POSTPONED => __( 'Postponed', 'mime-simple-events-calendar' ),
			default => '',
		};
	}
}
