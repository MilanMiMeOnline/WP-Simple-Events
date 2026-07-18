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
use Exception;

/**
 * Formats stored UTC boundaries for the public calendar feed.
 */
final readonly class CalendarEventDates {
	/**
	 * Return timed ISO values or half-open all-day date values.
	 *
	 * @param int    $start_utc UTC start timestamp.
	 * @param int    $end_utc   Inclusive UTC end timestamp.
	 * @param bool   $all_day   Whether visible times are omitted.
	 * @param string $timezone  Stored event timezone.
	 * @return array{start: string, end: string}|null
	 */
	public function format( int $start_utc, int $end_utc, bool $all_day, string $timezone ): ?array {
		if ( $start_utc <= 0 || $end_utc < $start_utc ) {
			return null;
		}

		try {
			$timezone_object = new DateTimeZone( $timezone );
			$start           = ( new DateTimeImmutable( '@' . $start_utc ) )->setTimezone( $timezone_object );
			$end             = ( new DateTimeImmutable( '@' . $end_utc ) )->setTimezone( $timezone_object );
		} catch ( Exception ) {
			return null;
		}

		if ( $all_day ) {
			return array(
				'start' => $start->format( 'Y-m-d' ),
				'end'   => $end->modify( '+1 second' )->format( 'Y-m-d' ),
			);
		}

		return array(
			'start' => $start->format( DATE_ATOM ),
			'end'   => $end->format( DATE_ATOM ),
		);
	}
}
