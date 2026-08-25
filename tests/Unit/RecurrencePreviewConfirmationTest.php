<?php
/**
 * Tests for recurrence preview confirmations.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEditScope;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Application\RecurrencePreviewConfirmation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves exact user, event, proposal, scope, target and window binding.
 */
#[CoversClass( RecurrencePreviewConfirmation::class )]
final class RecurrencePreviewConfirmationTest extends TestCase {
	private const SECRET = 'one-site-owned-secret-with-at-least-thirty-two-bytes';

	/**
	 * A token verifies only for the exact preview context that issued it.
	 */
	public function test_confirmation_is_bound_to_complete_preview_context(): void {
		$service  = new RecurrencePreviewConfirmation();
		$proposed = $this->aggregate();
		$revision = ( new RecurrenceAggregateRevision() )->token( null );
		$window   = RecurrenceGenerationWindow::between( '2027-01-04', '2027-02-04', 50 );
		$token    = $service->issue(
			42,
			7,
			$revision,
			$proposed,
			RecurrenceEditScope::COMPLETE_SERIES,
			null,
			$window,
			self::SECRET
		);

		self::assertTrue(
			$service->valid(
				$token,
				42,
				7,
				$revision,
				$proposed,
				RecurrenceEditScope::COMPLETE_SERIES,
				null,
				$window,
				self::SECRET
			)
		);
		self::assertFalse(
			$service->valid(
				$token,
				42,
				8,
				$revision,
				$proposed,
				RecurrenceEditScope::COMPLETE_SERIES,
				null,
				$window,
				self::SECRET
			)
		);
		self::assertFalse(
			$service->valid(
				$token,
				42,
				7,
				$revision,
				$this->aggregate( 2 ),
				RecurrenceEditScope::COMPLETE_SERIES,
				null,
				$window,
				self::SECRET
			)
		);
		self::assertFalse( $service->valid( 'invalid', 42, 7, $revision, $proposed, RecurrenceEditScope::COMPLETE_SERIES, null, $window, self::SECRET ) );
	}

	/**
	 * Return one minimal recurrence proposal.
	 *
	 * @param int $interval Daily interval.
	 */
	private function aggregate( int $interval = 1 ): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04T19:00:00', '2027-01-04T21:00:00', false, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			'019c1d83-1798-4fac-a66d-ae8d67c46319',
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $range->start_local(), $range, RecurrenceRule::daily( $interval ) ) )
		);
	}
}
