<?php
/**
 * Public calendar feed event formatting.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Calendar;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use WP_Post;
use WP_Term;

/**
 * Converts one public event post into a text-only FullCalendar event object.
 */
final readonly class CalendarEventFormatter {
	/**
	 * Create the feed formatter.
	 *
	 * @param CalendarEventDates $dates Calendar date formatter.
	 */
	public function __construct( private CalendarEventDates $dates = new CalendarEventDates() ) {}

	/**
	 * Format one event, omitting corrupt stored records.
	 *
	 * @param WP_Post $event Public event post.
	 * @return array<string, mixed>|null
	 */
	public function format( WP_Post $event ): ?array {
		if ( EventPostType::POST_TYPE !== $event->post_type ) {
			return null;
		}

		$all_day = $this->boolean_meta( $event->ID, EventMeta::ALL_DAY );
		$dates   = $this->dates->format(
			$this->integer_meta( $event->ID, EventMeta::START_UTC ),
			$this->integer_meta( $event->ID, EventMeta::END_UTC ),
			$all_day,
			$this->string_meta( $event->ID, EventMeta::TIMEZONE )
		);

		if ( null === $dates ) {
			return null;
		}

		$title     = trim( wp_strip_all_tags( get_the_title( $event ), true ) );
		$permalink = get_permalink( $event );
		$status    = EventStatus::tryFrom( $this->string_meta( $event->ID, EventMeta::STATUS ) ) ?? EventStatus::SCHEDULED;
		$venue     = $this->string_meta( $event->ID, EventMeta::VENUE );
		$extended  = array(
			'categories' => $this->category_slugs( $event->ID ),
		);

		if ( '' === $title || '' === $permalink ) {
			return null;
		}

		if ( '' !== $venue ) {
			$extended['venue'] = $venue;
		}

		return array(
			'id'            => $event->ID,
			'title'         => $title,
			'start'         => $dates['start'],
			'end'           => $dates['end'],
			'allDay'        => $all_day,
			'status'        => $status->value,
			'url'           => $permalink,
			'extendedProps' => $extended,
		);
	}

	/**
	 * Return normalized category slugs only.
	 *
	 * @param int $event_id Event post ID.
	 * @return string[]
	 */
	private function category_slugs( int $event_id ): array {
		$terms = get_the_terms( $event_id, EventTaxonomies::CATEGORY );

		if ( false === $terms || is_wp_error( $terms ) ) {
			return array();
		}

		return array_values( array_map( static fn ( WP_Term $term ): string => $term->slug, $terms ) );
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
