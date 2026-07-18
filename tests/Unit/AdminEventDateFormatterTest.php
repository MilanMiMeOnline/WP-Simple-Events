<?php
/**
 * Tests for compact admin event date formatting.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use DateTimeImmutable;
use MiMe\WPSimpleEvents\Admin\AdminEventDateFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminEventDateFormatter::class )]
/**
 * Verifies timezone-aware compact values used by the Events overview.
 */
final class AdminEventDateFormatterTest extends TestCase {
	/**
	 * Timed values use the captured timezone and WordPress formats.
	 */
	public function test_formats_timed_boundary_in_event_timezone(): void {
		$timestamp = ( new DateTimeImmutable( '2026-07-20T09:30:00+02:00' ) )->getTimestamp();

		self::assertSame(
			'2026-07-20 09:30',
			( new AdminEventDateFormatter() )->format( $timestamp, false, 'Europe/Brussels' )
		);
	}

	/**
	 * All-day boundaries omit the synthetic storage time.
	 */
	public function test_all_day_boundary_omits_time(): void {
		$timestamp = ( new DateTimeImmutable( '2026-07-20T23:59:59+02:00' ) )->getTimestamp();

		self::assertSame(
			'2026-07-20',
			( new AdminEventDateFormatter() )->format( $timestamp, true, 'Europe/Brussels' )
		);
	}

	/**
	 * Corrupt boundaries render as empty instead of emitting a warning.
	 */
	public function test_rejects_invalid_timestamp_or_timezone(): void {
		$formatter = new AdminEventDateFormatter();

		self::assertSame( '', $formatter->format( 0, false, 'Europe/Brussels' ) );
		self::assertSame( '', $formatter->format( 1_800_000_000, false, 'Not/A-Timezone' ) );
	}
}
