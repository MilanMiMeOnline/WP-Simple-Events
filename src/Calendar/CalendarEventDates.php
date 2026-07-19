<?php
/**
 * FullCalendar event date formatting.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;

/**
 * Formats canonical wall-time values for the public calendar feed.
 */
final readonly class CalendarEventDates {
	/**
	 * Return floating UI values and explicit machine presentation metadata.
	 *
	 * @param string $start_local Canonical local start.
	 * @param string $end_local   Canonical local inclusive end.
	 * @param int    $start_utc   UTC start timestamp.
	 * @param int    $end_utc     Inclusive UTC end timestamp.
	 * @param bool   $all_day     Whether visible times are omitted.
	 * @param string $timezone    Stored event timezone.
	 * @return array{start: string, end: string, timezone: string, start_instant: string|null, end_instant: string|null}|null
	 */
	public function format(
		string $start_local,
		string $end_local,
		int $start_utc,
		int $end_utc,
		bool $all_day,
		string $timezone
	): ?array {
		if ( $start_utc <= 0 || $end_utc < $start_utc ) {
			return null;
		}

		try {
			$range = EventDateRange::from_local( $start_local, $end_local, $all_day, $timezone );
		} catch ( InvalidArgumentException ) {
			return null;
		}

		if ( $range->start_utc() !== $start_utc || $range->end_utc() !== $end_utc ) {
			return null;
		}

		if ( $all_day ) {
			$exclusive_end = ( new DateTimeImmutable( $range->end_local(), new DateTimeZone( 'UTC' ) ) )->modify( '+1 day' );

			return array(
				'start'         => $range->start_local(),
				'end'           => $exclusive_end->format( 'Y-m-d' ),
				'timezone'      => $range->timezone(),
				'start_instant' => null,
				'end_instant'   => null,
			);
		}

		$timezone_object = new DateTimeZone( $range->timezone() );
		$start           = ( new DateTimeImmutable( '@' . $range->start_utc() ) )->setTimezone( $timezone_object );
		$end             = ( new DateTimeImmutable( '@' . $range->end_utc() ) )->setTimezone( $timezone_object );

		return array(
			'start'         => $range->start_local(),
			'end'           => $range->end_local(),
			'timezone'      => $range->timezone(),
			'start_instant' => $start->format( DATE_ATOM ),
			'end_instant'   => $end->format( DATE_ATOM ),
		);
	}
}
