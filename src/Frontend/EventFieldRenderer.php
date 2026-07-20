<?php
/**
 * Named public event-field rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Renders the stable semantic fragments consumed by all presentation hosts.
 */
final class EventFieldRenderer {
	/**
	 * Event content currently inside the WordPress content pipeline.
	 *
	 * @var array<int, true>
	 */
	private static array $rendering_content = array();

	/**
	 * Render the event title with an allowlisted heading level.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 * @param string            $heading      Allowlisted heading element.
	 * @param string            $id           Optional element ID.
	 * @param bool              $link         Link the title to the event.
	 */
	public function title(
		EventPresentation $presentation,
		string $heading = 'h2',
		string $id = '',
		bool $link = false
	): string {
		if ( ! $this->fields_visible( $presentation ) ) {
			return '';
		}

		$heading = in_array( $heading, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $heading : 'h2';
		$id_attr = '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '';
		$title   = esc_html( $presentation->title );

		if ( $link && '' !== $presentation->permalink ) {
			$title = '<a href="' . esc_url( $presentation->permalink ) . '">' . $title . '</a>';
		}

		return '<' . $heading . ' class="wpse-single-event-title"' . $id_attr . '>' . $title . '</' . $heading . '>';
	}

	/**
	 * Render the featured image with an optional event permalink.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 * @param string            $size         Registered WordPress image size.
	 * @param bool              $link         Link the image to the event.
	 * @param string            $alt_mode     Attachment alt text or decorative output.
	 */
	public function featured_image(
		EventPresentation $presentation,
		string $size = 'large',
		bool $link = false,
		string $alt_mode = 'attachment'
	): string {
		if ( ! $this->fields_visible( $presentation ) || ! $presentation->has_featured_image ) {
			return '';
		}

		$size       = 1 === preg_match( '/^[a-z0-9_-]{1,64}$/D', $size ) ? $size : 'large';
		$attributes = 'decorative' === $alt_mode ? array( 'alt' => '' ) : array();
		$image      = wp_kses_post( get_the_post_thumbnail( $presentation->event, $size, $attributes ) );

		if ( '' === $image ) {
			return '';
		}

		if ( $link && '' !== $presentation->permalink ) {
			$image = '<a class="wpse-event-image-link" href="' . esc_url( $presentation->permalink ) . '">' . $image . '</a>';
		}

		return '<div class="wpse-single-event-image">' . $image . '</div>';
	}

	/**
	 * Render the localized date range and optional captured timezone.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 * @param bool              $show_label   Whether to render the visible field label.
	 * @param string            $label        Optional plain-text label override.
	 */
	public function date_time(
		EventPresentation $presentation,
		bool $show_label = true,
		string $label = ''
	): string {
		if ( ! $this->fields_visible( $presentation ) || null === $presentation->date ) {
			return '';
		}

		$visible_label = '' !== $label ? $label : __( 'Date and time:', 'wp-simple-events' );
		$date          = '<p class="wpse-event-date">'
			. ( $show_label ? '<span class="wpse-event-label">' . esc_html( $visible_label ) . '</span> ' : '' )
			. '<time datetime="' . esc_attr( $presentation->date->start_iso )
			. '" data-wpse-end="' . esc_attr( $presentation->date->end_iso ) . '">'
			. esc_html( $presentation->date->label ) . '</time>';

		if ( '' !== $presentation->date->timezone_label ) {
			$date .= ' <span class="wpse-event-timezone">' . esc_html( $presentation->date->timezone_label ) . '</span>';
		}

		return $date . '</p>';
	}

	/**
	 * Render only exceptional event statuses.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 */
	public function status( EventPresentation $presentation ): string {
		if ( ! $this->fields_visible( $presentation ) ) {
			return '';
		}

		$label = match ( $presentation->status ) {
			EventStatus::CANCELLED => __( 'Cancelled', 'wp-simple-events' ),
			EventStatus::POSTPONED => __( 'Postponed', 'wp-simple-events' ),
			default => '',
		};

		return '' !== $label && null !== $presentation->status
			? '<p class="wpse-event-status wpse-event-status-' . esc_attr( $presentation->status->value )
				. '" role="status">' . esc_html( $label ) . '</p>'
			: '';
	}

	/**
	 * Render the named venue.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 * @param bool              $show_label   Whether to render the visible field label.
	 * @param string            $label        Optional plain-text label override.
	 */
	public function venue(
		EventPresentation $presentation,
		bool $show_label = true,
		string $label = ''
	): string {
		$visible_label = '' !== $label ? $label : __( 'Location:', 'wp-simple-events' );

		return $this->fields_visible( $presentation ) && '' !== $presentation->venue
			? '<p class="wpse-event-venue">'
				. ( $show_label ? '<span class="wpse-event-label">' . esc_html( $visible_label ) . '</span> ' : '' )
				. esc_html( $presentation->venue ) . '</p>'
			: '';
	}

	/**
	 * Render the multiline postal address.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 */
	public function address( EventPresentation $presentation ): string {
		return $this->fields_visible( $presentation ) && '' !== $presentation->address
			? '<address class="wpse-event-address">' . nl2br( esc_html( $presentation->address ) ) . '</address>'
			: '';
	}

	/**
	 * Render the location/route action.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 * @param string            $label        Optional plain-text action label override.
	 */
	public function location_action( EventPresentation $presentation, string $label = '' ): string {
		$visible_label = '' !== $label ? $label : __( 'View location', 'wp-simple-events' );

		return $this->fields_visible( $presentation ) && '' !== $presentation->location_url
			? '<p class="wpse-event-location-link"><a href="' . esc_url( $presentation->location_url ) . '">'
				. esc_html( $visible_label ) . '</a></p>'
			: '';
	}

	/**
	 * Render event content through WordPress while blocking recursive fields.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 */
	public function content( EventPresentation $presentation ): string {
		$event_id = $presentation->event->ID;

		if ( ! $this->fields_visible( $presentation )
			|| isset( self::$rendering_content[ $event_id ] )
			|| '' === trim( $presentation->event->post_content )
		) {
			return '';
		}

		self::$rendering_content[ $event_id ] = true;

		try {
			$content = trim( (string) apply_filters( 'the_content', $presentation->event->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public event content uses WordPress' core content pipeline.
		} finally {
			unset( self::$rendering_content[ $event_id ] );
		}

		return '' !== $content
			? '<div class="wpse-single-event-content">' . wp_kses_post( $content ) . '</div>'
			: '';
	}

	/**
	 * Render the WordPress excerpt through its normal public filters.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 */
	public function excerpt( EventPresentation $presentation ): string {
		if ( ! $this->fields_visible( $presentation ) ) {
			return '';
		}

		$excerpt = trim( (string) apply_filters( 'the_excerpt', get_the_excerpt( $presentation->event ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public excerpts use WordPress' core excerpt pipeline.

		return '' !== $excerpt
			? '<div class="wpse-event-excerpt">' . wp_kses_post( $excerpt ) . '</div>'
			: '';
	}

	/**
	 * Render the externally configured event action.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 * @param string            $label        Optional plain-text action label override.
	 */
	public function external_action( EventPresentation $presentation, string $label = '' ): string {
		if ( ! $this->fields_visible( $presentation ) || '' === $presentation->event_url ) {
			return '';
		}

		$label = '' !== $label
			? $label
			: ( '' !== $presentation->event_url_label
				? $presentation->event_url_label
				: __( 'More event information', 'wp-simple-events' ) );

		return '<p class="wpse-event-action"><a class="wpse-event-action-link" href="'
			. esc_url( $presentation->event_url ) . '">' . esc_html( $label ) . '</a></p>';
	}

	/**
	 * Render linked event categories.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 * @param bool              $show_label   Whether to render the visible field label.
	 * @param string            $label        Optional plain-text label override.
	 */
	public function categories(
		EventPresentation $presentation,
		bool $show_label = true,
		string $label = ''
	): string {
		return $this->terms(
			$presentation,
			$presentation->categories,
			'wpse-event-categories',
			'' !== $label ? $label : __( 'Categories:', 'wp-simple-events' ),
			$show_label
		);
	}

	/**
	 * Render linked event tags.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 * @param bool              $show_label   Whether to render the visible field label.
	 * @param string            $label        Optional plain-text label override.
	 */
	public function tags(
		EventPresentation $presentation,
		bool $show_label = true,
		string $label = ''
	): string {
		return $this->terms(
			$presentation,
			$presentation->tags,
			'wpse-event-tags',
			'' !== $label ? $label : __( 'Tags:', 'wp-simple-events' ),
			$show_label
		);
	}

	/**
	 * Render one named term collection.
	 *
	 * @param EventPresentation       $presentation Resolved event presentation.
	 * @param EventTermPresentation[] $terms        Public term destinations.
	 * @param string                  $css_class    Stable component class.
	 * @param string                  $label        Translated visible label.
	 * @param bool                    $show_label   Whether to render the visible label.
	 */
	private function terms(
		EventPresentation $presentation,
		array $terms,
		string $css_class,
		string $label,
		bool $show_label
	): string {
		if ( ! $this->fields_visible( $presentation ) || array() === $terms ) {
			return '';
		}

		$links = array_map(
			static fn ( EventTermPresentation $term ): string => '<a href="' . esc_url( $term->url ) . '">'
				. esc_html( $term->name ) . '</a>',
			$terms
		);

		return '<p class="' . esc_attr( $css_class ) . '">'
			. ( $show_label ? '<span class="wpse-event-label">' . esc_html( $label ) . '</span> ' : '' )
			. implode( '<span aria-hidden="true">, </span>', $links ) . '</p>';
	}

	/**
	 * Determine whether an atomic field may expose this context.
	 *
	 * @param EventPresentation $presentation Resolved event presentation.
	 */
	private function fields_visible( EventPresentation $presentation ): bool {
		return ! post_password_required( $presentation->event );
	}
}
