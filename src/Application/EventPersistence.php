<?php
/**
 * Event metadata persistence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Content\EventMeta;

/**
 * Persists only values that have passed central validation.
 */
final class EventPersistence {
	/**
	 * Replace the full event metadata record in one controlled operation.
	 *
	 * @param int                $post_id Event post ID.
	 * @param ValidatedEventData $data    Validated event data.
	 */
	public function persist( int $post_id, ValidatedEventData $data ): void {
		$range = $data->date_range;

		if ( null === $range ) {
			delete_post_meta( $post_id, EventMeta::START_LOCAL );
			delete_post_meta( $post_id, EventMeta::END_LOCAL );
			delete_post_meta( $post_id, EventMeta::START_UTC );
			delete_post_meta( $post_id, EventMeta::END_UTC );
		} else {
			update_post_meta( $post_id, EventMeta::START_LOCAL, $range->start_local() );
			update_post_meta( $post_id, EventMeta::END_LOCAL, $range->end_local() );
			update_post_meta( $post_id, EventMeta::START_UTC, $range->start_utc() );
			update_post_meta( $post_id, EventMeta::END_UTC, $range->end_utc() );
		}

		update_post_meta( $post_id, EventMeta::ALL_DAY, $data->all_day );
		update_post_meta( $post_id, EventMeta::TIMEZONE, $data->timezone );
		update_post_meta( $post_id, EventMeta::STATUS, $data->status->value );

		$this->update_optional( $post_id, EventMeta::VENUE, $data->venue );
		$this->update_optional( $post_id, EventMeta::ADDRESS, $data->address );
		$this->update_optional( $post_id, EventMeta::LOCATION_URL, $data->location_url );
		$this->update_optional( $post_id, EventMeta::EVENT_URL, $data->event_url );
		delete_post_meta( $post_id, EventMeta::DATES_NEED_REVIEW );
	}

	/**
	 * Keep optional empty values out of the database.
	 *
	 * @param int    $post_id Event post ID.
	 * @param string $meta_key Registered meta key.
	 * @param string $value    Validated value.
	 */
	private function update_optional( int $post_id, string $meta_key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}
}
