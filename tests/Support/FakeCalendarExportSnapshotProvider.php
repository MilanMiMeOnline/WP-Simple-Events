<?php
/**
 * Deterministic calendar export snapshot provider.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshotProvider;

/** Returns one configured snapshot and records strict endpoint lookups. */
final class FakeCalendarExportSnapshotProvider implements CalendarExportSnapshotProvider {
	/**
	 * Configured public snapshot, or null for an ineligible selection.
	 *
	 * @var CalendarExportSnapshot|null
	 */
	public ?CalendarExportSnapshot $snapshot = null;

	/**
	 * Recorded strict endpoint lookups.
	 *
	 * @var list<array{event_id: int, public_key: string|null}>
	 */
	public array $requests = array();

	/**
	 * Return the configured snapshot.
	 *
	 * @param int         $event_id   Canonical event post ID.
	 * @param string|null $public_key Exact occurrence key, or null.
	 */
	public function resolve( int $event_id, ?string $public_key = null ): ?CalendarExportSnapshot {
		$this->requests[] = array(
			'event_id'   => $event_id,
			'public_key' => $public_key,
		);

		return $this->snapshot;
	}
}
