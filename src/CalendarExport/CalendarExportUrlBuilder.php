<?php
/**
 * Same-origin calendar export URL construction.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

/** Builds the strict endpoint query from one already-public snapshot. */
final class CalendarExportUrlBuilder {
	/**
	 * Build a public ICS download URL.
	 *
	 * @param CalendarExportSnapshot $snapshot Eligible public snapshot.
	 */
	public function build( CalendarExportSnapshot $snapshot ): string {
		$query = array(
			CalendarExportController::EXPORT_QUERY_VAR => 'ics',
			CalendarExportController::EVENT_QUERY_VAR  => $snapshot->event_id,
		);

		if ( 'one-off' !== $snapshot->identity->recurrence_id() ) {
			$query[ CalendarExportController::OCCURRENCE_QUERY_VAR ] = $snapshot->identity->public_key();
		}

		return esc_url_raw(
			add_query_arg( $query, home_url( '/' ) ),
			array( 'http', 'https' )
		);
	}
}
