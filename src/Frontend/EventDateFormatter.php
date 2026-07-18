<?php
/**
 * Public event date formatting.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Formats stored UTC boundaries in the event's captured timezone.
 */
final class EventDateFormatter {
	/**
	 * Format one validated event date range for public output.
	 *
	 * @param int    $start_utc UTC start timestamp.
	 * @param int    $end_utc   Inclusive UTC end timestamp.
	 * @param bool   $all_day   Whether visible times are omitted.
	 * @param string $timezone  Stored event timezone.
	 */
	public function format(
		int $start_utc,
		int $end_utc,
		bool $all_day,
		string $timezone
	): ?EventDatePresentation {
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

		$date_format = $this->option_format( 'date_format', 'F j, Y' );
		$time_format = $this->option_format( 'time_format', 'H:i' );
		$start_date  = $this->format_timestamp( $date_format, $start_utc, $timezone_object );
		$end_date    = $this->format_timestamp( $date_format, $end_utc, $timezone_object );

		if ( '' === $start_date || '' === $end_date ) {
			return null;
		}

		if ( $all_day ) {
			$label = $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' )
				? $start_date
				: $start_date . ' – ' . $end_date;
		} else {
			$start_time = $this->format_timestamp( $time_format, $start_utc, $timezone_object );
			$end_time   = $this->format_timestamp( $time_format, $end_utc, $timezone_object );

			if ( '' === $start_time || '' === $end_time ) {
				return null;
			}

			if ( $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' ) ) {
				$label = $start_date . ', ' . $start_time;

				if ( $start_utc !== $end_utc ) {
					$label .= ' – ' . $end_time;
				}
			} else {
				$label = $start_date . ', ' . $start_time . ' – ' . $end_date . ', ' . $end_time;
			}
		}

		return new EventDatePresentation(
			$label,
			$all_day ? $start->format( 'Y-m-d' ) : $start->format( DATE_ATOM ),
			$all_day ? $end->format( 'Y-m-d' ) : $end->format( DATE_ATOM )
		);
	}

	/**
	 * Read a non-empty WordPress date/time format.
	 *
	 * @param string $option_name WordPress option name.
	 * @param string $fallback    Safe fallback format.
	 */
	private function option_format( string $option_name, string $fallback ): string {
		$value = get_option( $option_name, $fallback );

		return is_string( $value ) && '' !== $value ? $value : $fallback;
	}

	/**
	 * Format a timestamp and reject the unlikely WordPress failure value.
	 *
	 * @param string       $format    WordPress/PHP date format.
	 * @param int          $timestamp Unix timestamp.
	 * @param DateTimeZone $timezone  Event timezone.
	 */
	private function format_timestamp( string $format, int $timestamp, DateTimeZone $timezone ): string {
		$value = wp_date( $format, $timestamp, $timezone );

		return is_string( $value ) ? $value : '';
	}
}
