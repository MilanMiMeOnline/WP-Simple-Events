<?php
/**
 * Complete recurrence aggregate.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;

/**
 * Validates one recurrence definition and all of its identity-bound exceptions.
 */
final readonly class RecurrenceAggregate {
	public const SCHEMA_VERSION = 1;
	public const MAX_SEGMENTS   = 100;
	public const MAX_MANUALS    = 1_000;
	public const MAX_EXCLUSIONS = 1_000;
	public const MAX_OVERRIDES  = 1_000;

	/**
	 * Create one complete version-one aggregate.
	 *
	 * @param string $series_uid       Canonical immutable series UUID.
	 * @param string $timezone         Canonical timezone shared by every effective range.
	 * @param array  $segments         Chronological schedule segments, starting with ID zero.
	 * @param array  $manuals          Manual added occurrences.
	 * @param array  $exclusions       Reversible generated-slot exclusions.
	 * @param array  $overrides        Sparse occurrence overrides.
	 * @phpstan-param list<ScheduleSegment> $segments
	 * @phpstan-param list<ManualOccurrence> $manuals
	 * @phpstan-param list<OccurrenceExclusion> $exclusions
	 * @phpstan-param list<OccurrenceOverride> $overrides
	 * @throws InvalidArgumentException When any aggregate invariant is violated.
	 */
	public static function create(
		string $series_uid,
		string $timezone,
		array $segments,
		array $manuals = array(),
		array $exclusions = array(),
		array $overrides = array()
	): self {
		self::validate_collection( $segments, ScheduleSegment::class, 1, self::MAX_SEGMENTS );
		self::validate_collection( $manuals, ManualOccurrence::class, 0, self::MAX_MANUALS );
		self::validate_collection( $exclusions, OccurrenceExclusion::class, 0, self::MAX_EXCLUSIONS );
		self::validate_collection( $overrides, OccurrenceOverride::class, 0, self::MAX_OVERRIDES );

		if ( strtolower( trim( $series_uid ) ) !== $series_uid ) {
			throw new InvalidArgumentException( 'The recurrence aggregate series UID must be canonical.' );
		}

		if ( trim( $timezone ) !== $timezone ) {
			throw new InvalidArgumentException( 'The recurrence aggregate timezone must be canonical.' );
		}

		$first = $segments[0];

		if ( 0 !== $first->id ) {
			throw new InvalidArgumentException( 'The root recurrence segment must use ID zero.' );
		}

		if ( $first->anchor !== $first->template->start_local() ) {
			throw new InvalidArgumentException( 'The root recurrence segment anchor must match its template start.' );
		}

		if ( $timezone !== $first->template->timezone() ) {
			throw new InvalidArgumentException( 'The recurrence aggregate timezone must match its root segment.' );
		}

		OccurrenceIdentity::from( $series_uid, $first->anchor );

		self::validate_segments( $segments, $timezone );
		$manual_ids       = self::validate_manuals( $manuals, $series_uid, $timezone );
		$exclusions_by_id = self::validate_exclusions( $exclusions, $series_uid );
		self::validate_overrides( $overrides, $series_uid, $timezone, $manual_ids, $exclusions_by_id );

		return new self(
			$series_uid,
			$timezone,
			$segments,
			$manuals,
			$exclusions,
			$overrides
		);
	}

	/**
	 * Store one completely validated aggregate.
	 *
	 * @param string $series_uid Canonical series UUID.
	 * @param string $timezone   Canonical timezone.
	 * @param array  $segments   Validated schedule segments.
	 * @param array  $manuals    Validated manual occurrences.
	 * @param array  $exclusions Validated exclusions.
	 * @param array  $overrides  Validated sparse overrides.
	 * @phpstan-param list<ScheduleSegment> $segments
	 * @phpstan-param list<ManualOccurrence> $manuals
	 * @phpstan-param list<OccurrenceExclusion> $exclusions
	 * @phpstan-param list<OccurrenceOverride> $overrides
	 */
	private function __construct(
		public string $series_uid,
		public string $timezone,
		public array $segments,
		public array $manuals,
		public array $exclusions,
		public array $overrides
	) {}

	/**
	 * Validate one bounded homogeneous list.
	 *
	 * @param array        $items          Candidate list.
	 * @param class-string $required_class Required item class.
	 * @param int          $minimum        Minimum item count.
	 * @param int          $maximum        Maximum item count.
	 * @phpstan-param array<array-key, mixed> $items
	 * @throws InvalidArgumentException When list shape, count or item type is invalid.
	 */
	private static function validate_collection(
		array $items,
		string $required_class,
		int $minimum,
		int $maximum
	): void {
		if ( ! array_is_list( $items ) || count( $items ) < $minimum || count( $items ) > $maximum ) {
			throw new InvalidArgumentException( 'A recurrence aggregate collection is outside its supported bounds.' );
		}

		foreach ( $items as $item ) {
			if ( ! $item instanceof $required_class ) {
				throw new InvalidArgumentException( 'A recurrence aggregate collection contains an invalid item.' );
			}
		}
	}

	/**
	 * Validate chronological, unique segment identities and timezones.
	 *
	 * @param array  $segments Validated segment objects.
	 * @param string $timezone Aggregate timezone.
	 * @phpstan-param list<ScheduleSegment> $segments
	 * @throws InvalidArgumentException When segment ordering or identity is invalid.
	 */
	private static function validate_segments( array $segments, string $timezone ): void {
		$ids             = array();
		$anchors         = array();
		$previous_anchor = null;

		foreach ( $segments as $segment ) {
			if ( isset( $ids[ $segment->id ] ) || isset( $anchors[ $segment->anchor ] ) ) {
				throw new InvalidArgumentException( 'Recurrence segment IDs and anchors must be unique.' );
			}

			if ( null !== $previous_anchor && strcmp( $segment->anchor, $previous_anchor ) <= 0 ) {
				throw new InvalidArgumentException( 'Recurrence segments must use ascending original anchors.' );
			}

			if ( $timezone !== $segment->template->timezone() ) {
				throw new InvalidArgumentException( 'Every recurrence segment must use the aggregate timezone.' );
			}

			$ids[ $segment->id ]         = true;
			$anchors[ $segment->anchor ] = true;
			$previous_anchor             = $segment->anchor;
		}
	}

	/**
	 * Validate manual identities, timezones and uniqueness.
	 *
	 * @param array  $manuals    Manual occurrences.
	 * @param string $series_uid Canonical series UUID.
	 * @param string $timezone   Aggregate timezone.
	 * @phpstan-param list<ManualOccurrence> $manuals
	 * @return array<string, true>
	 * @throws InvalidArgumentException When a manual occurrence conflicts or drifts.
	 */
	private static function validate_manuals( array $manuals, string $series_uid, string $timezone ): array {
		$manual_ids = array();

		foreach ( $manuals as $manual ) {
			OccurrenceIdentity::from( $series_uid, $manual->recurrence_id );

			if ( isset( $manual_ids[ $manual->recurrence_id ] ) ) {
				throw new InvalidArgumentException( 'Manual occurrence identities must be unique.' );
			}

			if ( $timezone !== $manual->date_range->timezone() ) {
				throw new InvalidArgumentException( 'Every manual occurrence must use the aggregate timezone.' );
			}

			$manual_ids[ $manual->recurrence_id ] = true;
		}

		return $manual_ids;
	}

	/**
	 * Validate generated exclusion identities and uniqueness.
	 *
	 * @param array  $exclusions Exclusions.
	 * @param string $series_uid Canonical series UUID.
	 * @phpstan-param list<OccurrenceExclusion> $exclusions
	 * @return array<string, OccurrenceExclusionAction>
	 * @throws InvalidArgumentException When exclusion identities conflict.
	 */
	private static function validate_exclusions( array $exclusions, string $series_uid ): array {
		$by_id = array();

		foreach ( $exclusions as $exclusion ) {
			OccurrenceIdentity::from( $series_uid, $exclusion->recurrence_id );

			if ( isset( $by_id[ $exclusion->recurrence_id ] ) ) {
				throw new InvalidArgumentException( 'Occurrence exclusion identities must be unique.' );
			}

			$by_id[ $exclusion->recurrence_id ] = $exclusion->action;
		}

		return $by_id;
	}

	/**
	 * Validate sparse override identity, timezone and exclusion relationships.
	 *
	 * @param array                                    $overrides        Sparse overrides.
	 * @param string                                   $series_uid       Canonical series UUID.
	 * @param string                                   $timezone         Aggregate timezone.
	 * @param array<string, true>                      $manual_ids       Existing manual identities.
	 * @param array<string, OccurrenceExclusionAction> $exclusions_by_id Existing exclusions.
	 * @phpstan-param list<OccurrenceOverride> $overrides
	 * @throws InvalidArgumentException When an override conflicts or references missing manual data.
	 */
	private static function validate_overrides(
		array $overrides,
		string $series_uid,
		string $timezone,
		array $manual_ids,
		array $exclusions_by_id
	): void {
		$override_ids = array();

		foreach ( $overrides as $override ) {
			OccurrenceIdentity::from( $series_uid, $override->recurrence_id );

			if ( isset( $override_ids[ $override->recurrence_id ] ) ) {
				throw new InvalidArgumentException( 'Occurrence override identities must be unique.' );
			}

			if ( OccurrenceIdentity::is_manual_recurrence_id( $override->recurrence_id )
				&& ! isset( $manual_ids[ $override->recurrence_id ] )
			) {
				throw new InvalidArgumentException( 'A manual occurrence override requires its manual occurrence.' );
			}

			if ( OccurrenceExclusionAction::SKIP === ( $exclusions_by_id[ $override->recurrence_id ] ?? null ) ) {
				throw new InvalidArgumentException( 'A skipped occurrence cannot retain a public override.' );
			}

			$date_range = $override->fields()[ OccurrenceOverride::DATE_RANGE ] ?? null;

			if ( $date_range instanceof EventDateRange && $timezone !== $date_range->timezone() ) {
				throw new InvalidArgumentException( 'Every occurrence date override must use the aggregate timezone.' );
			}

			$override_ids[ $override->recurrence_id ] = true;
		}
	}
}
