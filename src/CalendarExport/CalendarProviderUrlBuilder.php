<?php
/**
 * Optional external calendar compose URL construction.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

use DateTimeImmutable;
use DateTimeZone;

/** Builds bounded one-off Google and best-effort Outlook compose links. */
final class CalendarProviderUrlBuilder {
	public const MAX_URL_BYTES = 8_192;

	/**
	 * Build one optional external provider link, or fail closed.
	 *
	 * @param CalendarProvider       $provider External provider.
	 * @param CalendarExportSnapshot $snapshot Eligible public snapshot.
	 */
	public function build( CalendarProvider $provider, CalendarExportSnapshot $snapshot ): string {
		if ( CalendarProvider::ICS === $provider
			|| ( ! $snapshot->date_range->all_day()
				&& $snapshot->date_range->end_utc() <= $snapshot->date_range->start_utc() )
		) {
			return '';
		}

		$url = match ( $provider ) {
			CalendarProvider::GOOGLE  => $this->google( $snapshot ),
			CalendarProvider::OUTLOOK => $this->outlook( $snapshot ),
		};

		return strlen( $url ) <= self::MAX_URL_BYTES ? $url : '';
	}

	/**
	 * Build Google's documented prefilled one-off event route.
	 *
	 * @param CalendarExportSnapshot $snapshot Eligible public snapshot.
	 */
	private function google( CalendarExportSnapshot $snapshot ): string {
		$args = array(
			'action'   => 'TEMPLATE',
			'text'     => $this->bounded( $snapshot->title, 200 ),
			'dates'    => $this->google_dates( $snapshot ),
			'details'  => $this->bounded( $this->external_text( $snapshot->description, ' - ' ), 1_000 ),
			'location' => $this->bounded( $this->external_text( $snapshot->location, ', ' ), 300 ),
		);

		if ( $this->iana_timezone( $snapshot->date_range->timezone() ) ) {
			$args['stz'] = $snapshot->date_range->timezone();
			$args['etz'] = $snapshot->date_range->timezone();
		}

		return esc_url_raw(
			add_query_arg( $args, 'https://calendar.google.com/calendar/render' ),
			array( 'https' )
		);
	}

	/**
	 * Build the isolated best-effort Outlook web compose route.
	 *
	 * @param CalendarExportSnapshot $snapshot Eligible public snapshot.
	 */
	private function outlook( CalendarExportSnapshot $snapshot ): string {
		$range = $snapshot->date_range;
		$args  = array(
			'path'     => '/calendar/action/compose',
			'rru'      => 'addevent',
			'subject'  => $this->bounded( $snapshot->title, 200 ),
			'startdt'  => $range->all_day() ? $range->start_local() : gmdate( 'Y-m-d\TH:i:s\Z', $range->start_utc() ),
			'enddt'    => $range->all_day()
				? $this->exclusive_all_day_end( $range->end_local(), $range->timezone(), 'Y-m-d' )
				: gmdate( 'Y-m-d\TH:i:s\Z', $range->end_utc() ),
			'body'     => $this->bounded( $this->external_text( $snapshot->description, ' - ' ), 1_000 ),
			'location' => $this->bounded( $this->external_text( $snapshot->location, ', ' ), 300 ),
		);

		if ( $range->all_day() ) {
			$args['allday'] = 'true';
		}

		return esc_url_raw(
			add_query_arg( $args, 'https://outlook.office.com/calendar/0/deeplink/compose' ),
			array( 'https' )
		);
	}

	/**
	 * Return Google's UTC timed or local all-day start/end pair.
	 *
	 * @param CalendarExportSnapshot $snapshot Eligible public snapshot.
	 */
	private function google_dates( CalendarExportSnapshot $snapshot ): string {
		$range = $snapshot->date_range;

		if ( $range->all_day() ) {
			return str_replace( '-', '', $range->start_local() ) . '/'
				. $this->exclusive_all_day_end( $range->end_local(), $range->timezone(), 'Ymd' );
		}

		return gmdate( 'Ymd\THis\Z', $range->start_utc() ) . '/'
			. gmdate( 'Ymd\THis\Z', $range->end_utc() );
	}

	/**
	 * Convert one inclusive all-day end to a provider's exclusive date.
	 *
	 * @param string $date     Canonical local end.
	 * @param string $timezone Captured timezone.
	 * @param string $format   Provider output format.
	 */
	private function exclusive_all_day_end( string $date, string $timezone, string $format ): string {
		return ( new DateTimeImmutable( $date, new DateTimeZone( $timezone ) ) )
			->modify( '+1 day' )
			->format( $format );
	}

	/**
	 * Determine whether timezone hints can truthfully name an IANA zone.
	 *
	 * @param string $timezone Captured event timezone.
	 */
	private function iana_timezone( string $timezone ): bool {
		return false !== ( new DateTimeZone( $timezone ) )->getLocation();
	}

	/**
	 * Replace snapshot line breaks with a visible URL-safe provider separator.
	 *
	 * WordPress removes encoded CR/LF sequences when a rendered href is escaped.
	 * Normalizing them before URL construction prevents adjacent fields from being
	 * silently concatenated while the local ICS export keeps its semantic lines.
	 *
	 * @param string $value     Validated snapshot value.
	 * @param string $separator Visible single-line separator.
	 */
	private function external_text( string $value, string $separator ): string {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$parts = array_filter(
			array_map( 'trim', explode( "\n", $value ) ),
			static fn( string $part ): bool => '' !== $part
		);

		return implode( $separator, $parts );
	}

	/**
	 * Truncate a validated snapshot value at a UTF-8-safe byte boundary.
	 *
	 * @param string $value   Snapshot value.
	 * @param int    $maximum Provider-specific byte budget.
	 */
	private function bounded( string $value, int $maximum ): string {
		$cut = substr( $value, 0, $maximum );

		while ( '' !== $cut && 1 !== preg_match( '//u', $cut ) ) {
			$cut = substr( $cut, 0, -1 );
		}

		return $cut;
	}
}
