<?php
/**
 * Occurrence projection table schema.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;

/**
 * Produces the deterministic dbDelta-compatible occurrence schema.
 */
final class OccurrenceTableSchema {
	/**
	 * Build table creation SQL from a trusted WordPress-prefixed table name.
	 *
	 * @param string $table_name      Complete table name.
	 * @param string $charset_collate WordPress charset/collation suffix.
	 * @throws InvalidArgumentException When the table identifier is not trusted.
	 */
	public function create_sql( string $table_name, string $charset_collate ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $table_name ) ) {
			throw new InvalidArgumentException( 'The occurrence table name is invalid.' );
		}

		$charset_collate = trim( $charset_collate );

		return "CREATE TABLE {$table_name} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "event_id bigint(20) unsigned NOT NULL,\n"
			. "public_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
			. "recurrence_id varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
			. "generation bigint(20) unsigned NOT NULL,\n"
			. "created_utc bigint(20) NOT NULL DEFAULT 0,\n"
			. "segment_id bigint(20) unsigned NOT NULL DEFAULT 0,\n"
			. "source varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
			. "start_local varchar(19) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
			. "end_local varchar(19) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
			. "start_utc bigint(20) NOT NULL,\n"
			. "end_utc bigint(20) NOT NULL,\n"
			. "timezone varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
			. "all_day tinyint(1) unsigned NOT NULL DEFAULT 0,\n"
			. "event_status varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY event_generation_key (event_id,generation,public_key),\n"
			. "KEY event_generation_start (event_id,generation,start_utc),\n"
			. "KEY generation_cleanup (created_utc,event_id,generation),\n"
			. "KEY start_utc (start_utc),\n"
			. "KEY end_utc (end_utc),\n"
			. "KEY status_start (event_status,start_utc),\n"
			. "KEY local_window (start_local,end_local)\n"
			. ')' . ( '' === $charset_collate ? '' : ' ' . $charset_collate ) . ';';
	}
}
