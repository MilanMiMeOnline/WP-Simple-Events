<?php
/**
 * WordPress occurrence table lifecycle.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Creates and removes the rebuildable occurrence projection.
 */
final class OccurrenceTable implements OccurrenceTableLifecycle {
	public const TABLE_SUFFIX = 'wpse_event_occurrences';

	/**
	 * Create or upgrade the current site's occurrence table through dbDelta.
	 */
	public function install(): bool {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $this->table_name();
		$sql        = ( new OccurrenceTableSchema() )->create_sql( $table_name, $wpdb->get_charset_collate() );

		dbDelta( $sql );

		return $this->exists();
	}

	/**
	 * Drop the table only from the explicit destructive uninstall path.
	 */
	public function drop(): bool {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return false;
		}

		$table_name = $this->table_name();
		$query      = $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name );

		if ( ! is_string( $query ) || false === $wpdb->query( $query ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared with the trusted identifier immediately above; plugin-owned table lifecycle has no WordPress API.
			return false;
		}

		return ! $this->exists();
	}

	/**
	 * Return the current site's trusted prefixed table name.
	 */
	public function table_name(): string {
		global $wpdb;

		return $wpdb instanceof \wpdb ? $wpdb->prefix . self::TABLE_SUFFIX : self::TABLE_SUFFIX;
	}

	/**
	 * Determine whether the exact current-site table exists.
	 */
	public function exists(): bool {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return false;
		}

		$table_name = $this->table_name();
		$query      = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );

		if ( ! is_string( $query ) ) {
			return false;
		}

		$found = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above; schema verification cannot use object caching.

		return is_string( $found ) && $table_name === $found;
	}
}
