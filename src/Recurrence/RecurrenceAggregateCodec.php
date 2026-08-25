<?php
/**
 * Versioned recurrence aggregate encoding.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Converts one complete aggregate to and from an exact bounded storage shape.
 */
final readonly class RecurrenceAggregateCodec {
	/**
	 * Create the aggregate codec.
	 *
	 * @param RecurrenceDefinitionCodec $definitions Canonical schedule codec.
	 */
	public function __construct(
		private RecurrenceDefinitionCodec $definitions = new RecurrenceDefinitionCodec()
	) {}

	/**
	 * Encode one validated aggregate into its canonical version-one shape.
	 *
	 * @param RecurrenceAggregate $aggregate Complete recurrence aggregate.
	 * @return array<string, mixed>
	 */
	public function encode( RecurrenceAggregate $aggregate ): array {
		$manuals    = $aggregate->manuals;
		$exclusions = $aggregate->exclusions;
		$overrides  = $aggregate->overrides;

		usort( $manuals, static fn ( ManualOccurrence $left, ManualOccurrence $right ): int => strcmp( $left->recurrence_id, $right->recurrence_id ) );
		usort( $exclusions, static fn ( OccurrenceExclusion $left, OccurrenceExclusion $right ): int => strcmp( $left->recurrence_id, $right->recurrence_id ) );
		usort( $overrides, static fn ( OccurrenceOverride $left, OccurrenceOverride $right ): int => strcmp( $left->recurrence_id, $right->recurrence_id ) );

		return array(
			'schema_version' => RecurrenceAggregate::SCHEMA_VERSION,
			'series_uid'     => $aggregate->series_uid,
			'timezone'       => $aggregate->timezone,
			'segments'       => array_map( fn ( ScheduleSegment $segment ): array => $this->encode_segment( $segment ), $aggregate->segments ),
			'manuals'        => array_map( fn ( ManualOccurrence $manual ): array => $this->encode_manual( $manual ), $manuals ),
			'exclusions'     => array_map( fn ( OccurrenceExclusion $exclusion ): array => $this->encode_exclusion( $exclusion ), $exclusions ),
			'overrides'      => array_map( fn ( OccurrenceOverride $override ): array => $this->encode_override( $override ), $overrides ),
		);
	}

	/**
	 * Decode one untrusted complete metadata value.
	 *
	 * @param mixed $value Untrusted stored aggregate.
	 * @throws InvalidArgumentException When any schema or domain invariant is invalid.
	 */
	public function decode( mixed $value ): RecurrenceAggregate {
		$value = $this->object_shape(
			$value,
			array( 'schema_version', 'series_uid', 'timezone', 'segments', 'manuals', 'exclusions', 'overrides' )
		);

		if ( RecurrenceAggregate::SCHEMA_VERSION !== ( $value['schema_version'] ?? null ) ) {
			throw new InvalidArgumentException( 'The recurrence aggregate schema version is unsupported.' );
		}

		$series_uid = $value['series_uid'] ?? null;
		$timezone   = $value['timezone'] ?? null;

		if ( ! is_string( $series_uid ) || ! is_string( $timezone ) ) {
			throw new InvalidArgumentException( 'The recurrence aggregate identity or timezone is invalid.' );
		}

		return RecurrenceAggregate::create(
			$series_uid,
			$timezone,
			$this->decode_segments( $value['segments'] ?? null, $timezone ),
			$this->decode_manuals( $value['manuals'] ?? null, $timezone ),
			$this->decode_exclusions( $value['exclusions'] ?? null ),
			$this->decode_overrides( $value['overrides'] ?? null, $timezone )
		);
	}

	/**
	 * Encode one schedule segment.
	 *
	 * @param ScheduleSegment $segment Validated schedule segment.
	 * @return array<string, mixed>
	 */
	private function encode_segment( ScheduleSegment $segment ): array {
		return array(
			'id'         => $segment->id,
			'anchor'     => $segment->anchor,
			'template'   => $this->encode_date_range( $segment->template ),
			'definition' => $this->definitions->encode( $segment->definition ),
		);
	}

	/**
	 * Encode one manual occurrence.
	 *
	 * @param ManualOccurrence $manual Validated manual occurrence.
	 * @return array<string, mixed>
	 */
	private function encode_manual( ManualOccurrence $manual ): array {
		return array(
			'recurrence_id' => $manual->recurrence_id,
			'date_range'    => $this->encode_date_range( $manual->date_range ),
			'status'        => $manual->status->value,
		);
	}

	/**
	 * Encode one generated-slot exclusion.
	 *
	 * @param OccurrenceExclusion $exclusion Validated occurrence exclusion.
	 * @return array<string, string>
	 */
	private function encode_exclusion( OccurrenceExclusion $exclusion ): array {
		return array(
			'recurrence_id' => $exclusion->recurrence_id,
			'action'        => $exclusion->action->value,
		);
	}

	/**
	 * Encode one sparse occurrence override.
	 *
	 * @param OccurrenceOverride $override Validated sparse occurrence override.
	 * @return array<string, mixed>
	 */
	private function encode_override( OccurrenceOverride $override ): array {
		$fields = array();

		foreach ( $override->fields() as $field => $value ) {
			if ( $value instanceof EventDateRange ) {
				$fields[ $field ] = $this->encode_date_range( $value );
			} elseif ( $value instanceof EventStatus ) {
				$fields[ $field ] = $value->value;
			} else {
				$fields[ $field ] = $value;
			}
		}

		return array(
			'recurrence_id' => $override->recurrence_id,
			'fields'        => $fields,
		);
	}

	/**
	 * Encode a validated range without duplicating the aggregate timezone.
	 *
	 * @param EventDateRange $range Validated event date range.
	 * @return array{start_local: string, end_local: string, all_day: bool}
	 */
	private function encode_date_range( EventDateRange $range ): array {
		return array(
			'start_local' => $range->start_local(),
			'end_local'   => $range->end_local(),
			'all_day'     => $range->all_day(),
		);
	}

	/**
	 * Decode a bounded non-empty schedule-segment list.
	 *
	 * @param mixed  $value    Untrusted segment collection.
	 * @param string $timezone Aggregate timezone candidate.
	 * @return list<ScheduleSegment>
	 * @throws InvalidArgumentException When the segment collection is invalid.
	 */
	private function decode_segments( mixed $value, string $timezone ): array {
		$items    = $this->bounded_list( $value, 1, RecurrenceAggregate::MAX_SEGMENTS );
		$segments = array();

		foreach ( $items as $item ) {
			$item   = $this->object_shape( $item, array( 'id', 'anchor', 'template', 'definition' ) );
			$id     = $item['id'] ?? null;
			$anchor = $item['anchor'] ?? null;

			if ( ! is_int( $id ) || ! is_string( $anchor ) ) {
				throw new InvalidArgumentException( 'A recurrence segment identity is invalid.' );
			}

			$segments[] = new ScheduleSegment(
				$id,
				$anchor,
				$this->decode_date_range( $item['template'] ?? null, $timezone ),
				$this->definitions->decode( $item['definition'] ?? null )
			);
		}

		return $segments;
	}

	/**
	 * Decode a bounded manual-occurrence list.
	 *
	 * @param mixed  $value    Untrusted manual collection.
	 * @param string $timezone Aggregate timezone candidate.
	 * @return list<ManualOccurrence>
	 * @throws InvalidArgumentException When the manual collection is invalid.
	 */
	private function decode_manuals( mixed $value, string $timezone ): array {
		$items   = $this->bounded_list( $value, 0, RecurrenceAggregate::MAX_MANUALS );
		$manuals = array();

		foreach ( $items as $item ) {
			$item          = $this->object_shape( $item, array( 'recurrence_id', 'date_range', 'status' ) );
			$recurrence_id = $item['recurrence_id'] ?? null;
			$status        = is_string( $item['status'] ?? null ) ? EventStatus::tryFrom( $item['status'] ) : null;

			if ( ! is_string( $recurrence_id ) || null === $status ) {
				throw new InvalidArgumentException( 'A manual occurrence identity or status is invalid.' );
			}

			$manuals[] = new ManualOccurrence(
				$recurrence_id,
				$this->decode_date_range( $item['date_range'] ?? null, $timezone ),
				$status
			);
		}

		return $manuals;
	}

	/**
	 * Decode a bounded generated exclusion list.
	 *
	 * @param mixed $value Untrusted exclusion collection.
	 * @return list<OccurrenceExclusion>
	 * @throws InvalidArgumentException When the exclusion collection is invalid.
	 */
	private function decode_exclusions( mixed $value ): array {
		$items      = $this->bounded_list( $value, 0, RecurrenceAggregate::MAX_EXCLUSIONS );
		$exclusions = array();

		foreach ( $items as $item ) {
			$item          = $this->object_shape( $item, array( 'recurrence_id', 'action' ) );
			$recurrence_id = $item['recurrence_id'] ?? null;
			$action        = is_string( $item['action'] ?? null ) ? OccurrenceExclusionAction::tryFrom( $item['action'] ) : null;

			if ( ! is_string( $recurrence_id ) || null === $action ) {
				throw new InvalidArgumentException( 'An occurrence exclusion identity or action is invalid.' );
			}

			$exclusions[] = new OccurrenceExclusion( $recurrence_id, $action );
		}

		return $exclusions;
	}

	/**
	 * Decode a bounded sparse override list.
	 *
	 * @param mixed  $value    Untrusted override collection.
	 * @param string $timezone Aggregate timezone candidate.
	 * @return list<OccurrenceOverride>
	 * @throws InvalidArgumentException When the override collection is invalid.
	 */
	private function decode_overrides( mixed $value, string $timezone ): array {
		$items     = $this->bounded_list( $value, 0, RecurrenceAggregate::MAX_OVERRIDES );
		$overrides = array();

		foreach ( $items as $item ) {
			$item          = $this->object_shape( $item, array( 'recurrence_id', 'fields' ) );
			$recurrence_id = $item['recurrence_id'] ?? null;
			$fields        = $this->object_shape( $item['fields'] ?? null, null );
			$this->validate_override_keys( $fields );

			if ( ! is_string( $recurrence_id ) ) {
				throw new InvalidArgumentException( 'An occurrence override identity is invalid.' );
			}

			$decoded_fields = array();

			foreach ( $fields as $field => $field_value ) {
				$decoded_fields[ $field ] = $this->decode_override_field( $field, $field_value, $timezone );
			}

			$overrides[] = OccurrenceOverride::from_fields( $recurrence_id, $decoded_fields );
		}

		return $overrides;
	}

	/**
	 * Decode one allowlisted sparse field value.
	 *
	 * @param string $field      Sparse field name.
	 * @param mixed  $value      Untrusted field value.
	 * @param string $timezone   Aggregate timezone candidate.
	 * @return EventDateRange|EventStatus|int|string
	 * @throws InvalidArgumentException When the sparse value is invalid.
	 */
	private function decode_override_field( string $field, mixed $value, string $timezone ): EventDateRange|EventStatus|int|string {
		if ( OccurrenceOverride::DATE_RANGE === $field ) {
			return $this->decode_date_range( $value, $timezone );
		}

		if ( OccurrenceOverride::STATUS === $field ) {
			$status = is_string( $value ) ? EventStatus::tryFrom( $value ) : null;

			if ( null === $status ) {
				throw new InvalidArgumentException( 'An occurrence override status is invalid.' );
			}

			return $status;
		}

		if ( OccurrenceOverride::FEATURED_IMAGE_ID === $field ) {
			if ( ! is_int( $value ) ) {
				throw new InvalidArgumentException( 'An occurrence override image ID is invalid.' );
			}

			return $value;
		}

		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'An occurrence override text value is invalid.' );
		}

		return $value;
	}

	/**
	 * Reject unsupported sparse override keys.
	 *
	 * @param array<string, mixed> $fields Untrusted sparse field map.
	 * @throws InvalidArgumentException When an unsupported key is present.
	 */
	private function validate_override_keys( array $fields ): void {
		$allowed = array(
			OccurrenceOverride::TITLE,
			OccurrenceOverride::NOTE,
			OccurrenceOverride::FEATURED_IMAGE_ID,
			OccurrenceOverride::DATE_RANGE,
			OccurrenceOverride::STATUS,
			OccurrenceOverride::VENUE,
			OccurrenceOverride::ADDRESS,
			OccurrenceOverride::LOCATION_URL,
			OccurrenceOverride::EVENT_URL,
			OccurrenceOverride::EVENT_URL_LABEL,
		);

		foreach ( array_keys( $fields ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				throw new InvalidArgumentException( 'The recurrence aggregate contains an unsupported override field.' );
			}
		}
	}

	/**
	 * Decode one exact canonical date range in the aggregate timezone.
	 *
	 * @param mixed  $value    Untrusted range shape.
	 * @param string $timezone Aggregate timezone candidate.
	 * @throws InvalidArgumentException When the date range is invalid.
	 */
	private function decode_date_range( mixed $value, string $timezone ): EventDateRange {
		$value   = $this->object_shape( $value, array( 'start_local', 'end_local', 'all_day' ) );
		$start   = $value['start_local'] ?? null;
		$end     = $value['end_local'] ?? null;
		$all_day = $value['all_day'] ?? null;

		if ( ! is_string( $start ) || ! is_string( $end ) || ! is_bool( $all_day ) ) {
			throw new InvalidArgumentException( 'A recurrence aggregate date range is invalid.' );
		}

		return EventDateRange::from_local( $start, $end, $all_day, $timezone );
	}

	/**
	 * Require one list before processing any of its bounded items.
	 *
	 * @param mixed $value   Untrusted collection.
	 * @param int   $minimum Minimum count.
	 * @param int   $maximum Maximum count.
	 * @return list<mixed>
	 * @throws InvalidArgumentException When the value is not a bounded list.
	 */
	private function bounded_list( mixed $value, int $minimum, int $maximum ): array {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) < $minimum || count( $value ) > $maximum ) {
			throw new InvalidArgumentException( 'A recurrence aggregate collection is outside its supported bounds.' );
		}

		return $value;
	}

	/**
	 * Require a string-keyed object shape and optionally its exact key set.
	 *
	 * @param mixed      $value         Untrusted object candidate.
	 * @param array|null $required_keys Exact keys, or null to validate shape only.
	 * @phpstan-param list<string>|null $required_keys
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When the value is not the required object shape.
	 */
	private function object_shape( mixed $value, ?array $required_keys ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( 'A recurrence aggregate object must be a keyed array.' );
		}

		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_string( $key ) ) {
				throw new InvalidArgumentException( 'A recurrence aggregate object contains a numeric key.' );
			}
		}

		if ( null !== $required_keys ) {
			$actual_keys = array_keys( $value );
			sort( $actual_keys, SORT_STRING );
			sort( $required_keys, SORT_STRING );

			if ( $actual_keys !== $required_keys ) {
				throw new InvalidArgumentException( 'A recurrence aggregate object contains missing or unknown fields.' );
			}
		}

		return $value;
	}
}
