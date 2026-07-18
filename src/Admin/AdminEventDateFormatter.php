<?php
/**
 * Event admin-list date formatting.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use DateTimeZone;
use Exception;

/**
 * Formats one UTC event boundary in its captured timezone.
 */
final class AdminEventDateFormatter {
	/**
	 * Format one stored event boundary for a compact admin column.
	 *
	 * @param int    $timestamp UTC timestamp.
	 * @param bool   $all_day   Whether visible time should be omitted.
	 * @param string $timezone  Stored event timezone.
	 */
	public function format( int $timestamp, bool $all_day, string $timezone ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}

		try {
			$timezone_object = new DateTimeZone( $timezone );
		} catch ( Exception ) {
			return '';
		}

		$date_format = $this->option_format( 'date_format', 'Y-m-d' );
		$format      = $all_day
			? $date_format
			: $date_format . ' ' . $this->option_format( 'time_format', 'H:i' );
		$value       = wp_date( $format, $timestamp, $timezone_object );

		return is_string( $value ) ? $value : '';
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
}
