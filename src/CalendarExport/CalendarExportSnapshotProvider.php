<?php
/**
 * Public calendar snapshot boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

/**
 * Resolves one eligible public event selection for calendar providers.
 */
interface CalendarExportSnapshotProvider {
	/**
	 * Resolve one public selection without falling back across contexts.
	 *
	 * @param int         $event_id   Canonical event post ID.
	 * @param string|null $public_key Exact occurrence key, or null for one-off.
	 */
	public function resolve( int $event_id, ?string $public_key = null ): ?CalendarExportSnapshot;
}
