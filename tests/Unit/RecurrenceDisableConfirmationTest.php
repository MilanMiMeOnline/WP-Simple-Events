<?php
/**
 * Tests for recurrence-disable preview confirmations.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\RecurrenceDisableConfirmation;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves exact survivor, user, event, revision and window binding.
 */
#[CoversClass( RecurrenceDisableConfirmation::class )]
final class RecurrenceDisableConfirmationTest extends TestCase {
	private const SECRET = 'one-site-owned-secret-with-at-least-thirty-two-bytes';

	/**
	 * A token verifies only for the exact destructive preview that issued it.
	 */
	public function test_confirmation_is_bound_to_exact_disable_context(): void {
		$service  = new RecurrenceDisableConfirmation();
		$revision = ( new RecurrenceAggregateRevision() )->token( null );
		$window   = RecurrenceGenerationWindow::between( '2027-01-04', '2027-02-04', 50 );
		$token    = $service->issue( 42, 7, $revision, '2027-01-06', $window, self::SECRET );

		self::assertTrue( $service->valid( $token, 42, 7, $revision, '2027-01-06', $window, self::SECRET ) );
		self::assertFalse( $service->valid( $token, 42, 8, $revision, '2027-01-06', $window, self::SECRET ) );
		self::assertFalse( $service->valid( $token, 42, 7, $revision, '2027-01-07', $window, self::SECRET ) );
		self::assertFalse( $service->valid( 'invalid', 42, 7, $revision, '2027-01-06', $window, self::SECRET ) );
	}
}
