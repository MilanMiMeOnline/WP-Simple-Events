<?php
/**
 * Tests for bounded calendar export snapshots.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Verifies that unsafe provider data cannot enter calendar generation. */
#[CoversClass( CalendarExportSnapshot::class )]
final class CalendarExportSnapshotTest extends TestCase {
	private const UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';

	/** A complete scheduled snapshot preserves only its validated values. */
	public function test_accepts_one_bounded_public_snapshot(): void {
		$snapshot = $this->snapshot();

		self::assertSame( 42, $snapshot->event_id );
		self::assertSame( 'Concert', $snapshot->title );
		self::assertSame( 'concert-2026-07-16', $snapshot->filename );
		self::assertSame( EventStatus::SCHEDULED, $snapshot->status );
	}

	/** Cancelled, unsafe and oversized values fail before provider formatting. */
	public function test_rejects_cancelled_or_unsafe_provider_values(): void {
		foreach (
			array(
				array( 'status' => EventStatus::CANCELLED ),
				array( 'url' => 'javascript:alert(1)' ),
				array( 'title' => str_repeat( 'a', CalendarExportSnapshot::MAX_TITLE_BYTES + 1 ) ),
				array( 'filename' => '../concert' ),
				array( 'description' => "Visible\0hidden" ),
			) as $changes
		) {
			try {
				$this->snapshot( $changes );
				self::fail( 'Expected the unsafe snapshot to be rejected.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/**
	 * Build one valid snapshot with selected replacements.
	 *
	 * @param array<string, mixed> $changes Replacement constructor values.
	 */
	private function snapshot( array $changes = array() ): CalendarExportSnapshot {
		$values = array_replace(
			array(
				'event_id'          => 42,
				'identity'          => OccurrenceIdentity::from( self::UID, 'one-off' ),
				'title'             => 'Concert',
				'url'               => 'https://example.com/events/concert/',
				'range'             => EventDateRange::from_local(
					'2026-07-16T19:30:00',
					'2026-07-16T21:30:00',
					false,
					'Europe/Brussels'
				),
				'status'            => EventStatus::SCHEDULED,
				'description'       => 'A visible description.',
				'location'          => 'Town Hall, Main Street 1',
				'last_modified_utc' => 1_784_220_000,
				'filename'          => 'concert-2026-07-16',
			),
			$changes
		);

		return new CalendarExportSnapshot(
			$values['event_id'],
			$values['identity'],
			$values['title'],
			$values['url'],
			$values['range'],
			$values['status'],
			$values['description'],
			$values['location'],
			$values['last_modified_utc'],
			$values['filename']
		);
	}
}
