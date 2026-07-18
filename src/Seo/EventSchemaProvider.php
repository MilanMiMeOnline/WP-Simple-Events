<?php
/**
 * WordPress event structured-data provider.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventDateFormatter;
use WP_Post;

/**
 * Reads one public event through WordPress APIs and creates its schema graph.
 */
final class EventSchemaProvider {
	/**
	 * Create the provider.
	 *
	 * @param EventSchemaBuilder $builder        Pure schema builder.
	 * @param EventDateFormatter $date_formatter Public date formatter.
	 */
	public function __construct(
		private readonly EventSchemaBuilder $builder = new EventSchemaBuilder(),
		private readonly EventDateFormatter $date_formatter = new EventDateFormatter()
	) {}

	/**
	 * Build schema only for a public, password-free event.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string, mixed>|null
	 */
	public function provide( int $event_id ): ?array {
		$event = get_post( $event_id );

		if (
			! $event instanceof WP_Post
			|| EventPostType::POST_TYPE !== $event->post_type
			|| 'publish' !== $event->post_status
			|| '' !== $event->post_password
		) {
			return null;
		}

		$dates  = $this->date_formatter->format(
			$this->integer_meta( $event_id, EventMeta::START_UTC ),
			$this->integer_meta( $event_id, EventMeta::END_UTC ),
			$this->boolean_meta( $event_id, EventMeta::ALL_DAY ),
			$this->string_meta( $event_id, EventMeta::TIMEZONE )
		);
		$status = EventStatus::tryFrom( $this->string_meta( $event_id, EventMeta::STATUS ) );
		$url    = esc_url_raw( get_permalink( $event ), array( 'http', 'https' ) );

		if ( null === $dates || null === $status || '' === $url ) {
			return null;
		}

		$image_url = get_the_post_thumbnail_url( $event, 'full' );

		return $this->builder->build(
			new EventSchemaInput(
				name: wp_strip_all_tags( get_the_title( $event ), true ),
				start_date: $dates->start_iso,
				end_date: $dates->end_iso,
				status: $status,
				url: $url,
				description: $this->description( $event ),
				image_url: is_string( $image_url ) ? $image_url : '',
				venue: $this->string_meta( $event_id, EventMeta::VENUE ),
				address: $this->string_meta( $event_id, EventMeta::ADDRESS )
			)
		);
	}

	/**
	 * Build a bounded plain-text summary from visible post text.
	 *
	 * @param WP_Post $event Public event post.
	 */
	private function description( WP_Post $event ): string {
		$source = '' !== trim( $event->post_excerpt ) ? $event->post_excerpt : $event->post_content;
		$text   = trim( wp_strip_all_tags( strip_shortcodes( $source ), true ) );

		return '' === $text ? '' : wp_trim_words( $text, 55, '…' );
	}

	/**
	 * Read scalar event metadata as a trimmed string.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Registered event metadata key.
	 */
	private function string_meta( int $event_id, string $meta_key ): string {
		$value = get_post_meta( $event_id, $meta_key, true );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Read numeric event metadata as an integer.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Registered event metadata key.
	 */
	private function integer_meta( int $event_id, string $meta_key ): int {
		$value = get_post_meta( $event_id, $meta_key, true );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Read the registered all-day value without trusting arbitrary truthy text.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Registered event metadata key.
	 */
	private function boolean_meta( int $event_id, string $meta_key ): bool {
		$value = get_post_meta( $event_id, $meta_key, true );

		return true === $value || 1 === $value || '1' === $value;
	}
}
