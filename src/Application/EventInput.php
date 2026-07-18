<?php
/**
 * Untrusted event editor input.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Carries event fields from an input adapter into the central validator.
 */
final readonly class EventInput {
	/**
	 * Store untrusted editor fields.
	 *
	 * @param string $start_date  Local start date.
	 * @param string $start_time  Local start time.
	 * @param string $end_date    Local end date.
	 * @param string $end_time    Local end time.
	 * @param bool   $all_day     All-day selection.
	 * @param string $timezone    Event timezone.
	 * @param string $venue       Venue name.
	 * @param string $address     Readable address.
	 * @param string $location_url External location URL.
	 * @param string $event_url   External event URL.
	 * @param string $status      Explicit event status.
	 */
	public function __construct(
		public string $start_date,
		public string $start_time,
		public string $end_date,
		public string $end_time,
		public bool $all_day,
		public string $timezone,
		public string $venue,
		public string $address,
		public string $location_url,
		public string $event_url,
		public string $status
	) {}

	/**
	 * Build editor components from canonical stored local values.
	 *
	 * @param string $start_local Canonical stored start.
	 * @param string $end_local   Canonical stored end.
	 * @param bool   $all_day     All-day selection.
	 * @param string $timezone    Event timezone.
	 * @param string $venue       Venue name.
	 * @param string $address     Readable address.
	 * @param string $location_url External location URL.
	 * @param string $event_url   External event URL.
	 * @param string $status      Explicit event status.
	 */
	public static function from_canonical(
		string $start_local,
		string $end_local,
		bool $all_day,
		string $timezone,
		string $venue,
		string $address,
		string $location_url,
		string $event_url,
		string $status
	): self {
		$start_parts = self::split_local( $start_local, $all_day );
		$end_parts   = self::split_local( $end_local, $all_day );

		return new self(
			$start_parts['date'],
			$start_parts['time'],
			$end_parts['date'],
			$end_parts['time'],
			$all_day,
			$timezone,
			$venue,
			$address,
			$location_url,
			$event_url,
			$status
		);
	}

	/**
	 * Split one canonical local value into native date and time controls.
	 *
	 * @param string $value   Canonical local value.
	 * @param bool   $all_day Whether the value represents an all-day event.
	 * @return array{date: string, time: string}
	 */
	private static function split_local( string $value, bool $all_day ): array {
		if ( '' === $value ) {
			return array(
				'date' => '',
				'time' => '',
			);
		}

		if ( $all_day ) {
			return array(
				'date' => substr( $value, 0, 10 ),
				'time' => '',
			);
		}

		$parts = explode( 'T', $value, 2 );

		return array(
			'date' => $parts[0],
			'time' => isset( $parts[1] ) ? substr( $parts[1], 0, 5 ) : '',
		);
	}
}
