<?php
/**
 * Event metadata persistence.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrenceProjector;

/**
 * Persists only values that have passed central validation.
 */
final class EventPersistence {
	/**
	 * Create the canonical persistence gateway.
	 *
	 * The projector is injected only by the production composition root so pure
	 * metadata tests and maintenance tools can remain independent of table state.
	 *
	 * @param EventOccurrenceProjector|null $projector         Derived occurrence projector.
	 * @param RecurrenceScheduleOwnership   $schedule_ownership Protected schedule ownership boundary.
	 */
	public function __construct(
		private readonly ?EventOccurrenceProjector $projector = null,
		private readonly RecurrenceScheduleOwnership $schedule_ownership = new RecurrenceScheduleOwnership()
	) {}

	/**
	 * Replace the full event metadata record in one controlled operation.
	 *
	 * @param int                $post_id Event post ID.
	 * @param ValidatedEventData $data    Validated event data.
	 */
	public function persist( int $post_id, ValidatedEventData $data ): void {
		if ( $this->schedule_ownership->owns( $post_id ) ) {
			$this->persist_recurring_series_fields( $post_id, $data );
			return;
		}

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
		$this->update_optional( $post_id, EventMeta::EVENT_URL_LABEL, $data->event_url_label );
		delete_post_meta( $post_id, EventMeta::DATES_NEED_REVIEW );

		if ( null !== $this->projector ) {
			$this->projector->project_one_off( $post_id, $range, $data->status );
		}
	}

	/**
	 * Keep schedule ownership with the recurrence aggregate on ordinary post saves.
	 *
	 * @param int                $post_id Event post ID.
	 * @param ValidatedEventData $data    Validated ordinary event fields.
	 */
	private function persist_recurring_series_fields( int $post_id, ValidatedEventData $data ): void {
		$previous_status = get_post_meta( $post_id, EventMeta::STATUS, true );

		update_post_meta( $post_id, EventMeta::STATUS, $data->status->value );
		$this->update_optional( $post_id, EventMeta::VENUE, $data->venue );
		$this->update_optional( $post_id, EventMeta::ADDRESS, $data->address );
		$this->update_optional( $post_id, EventMeta::LOCATION_URL, $data->location_url );
		$this->update_optional( $post_id, EventMeta::EVENT_URL, $data->event_url );
		$this->update_optional( $post_id, EventMeta::EVENT_URL_LABEL, $data->event_url_label );
		delete_post_meta( $post_id, EventMeta::DATES_NEED_REVIEW );

		if ( ! is_string( $previous_status ) || $previous_status !== $data->status->value ) {
			update_post_meta( $post_id, EventMeta::INDEX_DIRTY, true );
		}
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
