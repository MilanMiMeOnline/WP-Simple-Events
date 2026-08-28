<?php
/**
 * RFC 5545 calendar snapshot builder.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

use DateTimeImmutable;
use DateTimeZone;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Builds one standards-based VCALENDAR without WordPress or provider state.
 */
final class IcsCalendarBuilder {
	private const CRLF = "\r\n";

	/**
	 * Build one complete calendar file with one event.
	 *
	 * @param CalendarExportSnapshot $snapshot Validated provider snapshot.
	 */
	public function build( CalendarExportSnapshot $snapshot ): string {
		$range = $snapshot->date_range;
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//MiMe//MiMe Simple Events and Calendar//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:wpse-' . $snapshot->identity->public_key() . '@mime-simple-events-calendar',
			'DTSTAMP:' . $this->utc( $snapshot->last_modified_utc ),
			'LAST-MODIFIED:' . $this->utc( $snapshot->last_modified_utc ),
		);

		if ( $range->all_day() ) {
			$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $range->start_local() );
			$lines[] = 'DTEND;VALUE=DATE:' . $this->exclusive_all_day_end( $range->end_local(), $range->timezone() );
		} else {
			$lines[] = 'DTSTART:' . $this->utc( $range->start_utc() );

			if ( $range->end_utc() > $range->start_utc() ) {
				$lines[] = 'DTEND:' . $this->utc( $range->end_utc() );
			}
		}

		$lines[] = 'SUMMARY:' . $this->escape_text( $snapshot->title );

		if ( '' !== $snapshot->description ) {
			$lines[] = 'DESCRIPTION:' . $this->escape_text( $snapshot->description );
		}

		if ( '' !== $snapshot->location ) {
			$lines[] = 'LOCATION:' . $this->escape_text( $snapshot->location );
		}

		$lines[] = 'URL:' . $snapshot->canonical_url;
		$lines[] = EventStatus::POSTPONED === $snapshot->status ? 'STATUS:TENTATIVE' : 'STATUS:CONFIRMED';
		$lines[] = 'TRANSP:OPAQUE';
		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		return implode( self::CRLF, array_map( array( $this, 'fold_line' ), $lines ) ) . self::CRLF;
	}

	/**
	 * Return one UTC iCalendar date-time.
	 *
	 * @param int $timestamp Positive Unix timestamp.
	 */
	private function utc( int $timestamp ): string {
		return gmdate( 'Ymd\THis\Z', $timestamp );
	}

	/**
	 * Convert one inclusive local all-day end to RFC's exclusive end date.
	 *
	 * @param string $date     Canonical local end date.
	 * @param string $timezone Captured event timezone.
	 */
	private function exclusive_all_day_end( string $date, string $timezone ): string {
		return ( new DateTimeImmutable( $date, new DateTimeZone( $timezone ) ) )
			->modify( '+1 day' )
			->format( 'Ymd' );
	}

	/**
	 * Escape one RFC 5545 TEXT value after normalizing line endings.
	 *
	 * @param string $value Validated plain-text value.
	 */
	private function escape_text( string $value ): string {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = str_replace( '\\', '\\\\', $value );

		return str_replace(
			array( ';', ',', "\n" ),
			array( '\\;', '\\,', '\\n' ),
			$value
		);
	}

	/**
	 * Fold one content line at 75 octets.
	 *
	 * Continuation lines begin with one space, leaving 74 octets for content.
	 * The split point is moved before a UTF-8 continuation byte.
	 *
	 * @param string $line Unfolded content line.
	 */
	private function fold_line( string $line ): string {
		$parts            = array();
		$remaining        = $line;
		$remaining_length = strlen( $remaining );
		$limit            = 75;

		while ( $remaining_length > $limit ) {
			$cut = $limit;

			while ( $cut > 0 && ( ord( $remaining[ $cut ] ) & 0xC0 ) === 0x80 ) {
				--$cut;
			}

			if ( 0 === $cut ) {
				$cut = $limit;
			}

			$parts[]          = substr( $remaining, 0, $cut );
			$remaining        = substr( $remaining, $cut );
			$remaining_length = strlen( $remaining );
			$limit            = 74;
		}

		$parts[] = $remaining;

		return implode( self::CRLF . ' ', $parts );
	}
}
