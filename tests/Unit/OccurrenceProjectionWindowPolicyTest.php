<?php
/**
 * Tests for production projection-window policy.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionWindowPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Proves fresh, buffered and fail-closed coverage boundaries. */
#[CoversClass( OccurrenceProjectionWindowPolicy::class )]
final class OccurrenceProjectionWindowPolicyTest extends TestCase {
	/** Fresh production coverage uses the exact documented horizon. */
	public function test_builds_fresh_leap_safe_window(): void {
		$policy = new OccurrenceProjectionWindowPolicy();
		$window = $policy->fresh_window( '2027-02-28' );

		self::assertSame( '2027-02-28', $window->from_date() );
		self::assertSame( '2028-08-21', $window->through_date() );
		self::assertSame( 1_000, $window->max_rows() );
	}

	/** Minimum public coverage and buffered renewal remain distinct. */
	public function test_separates_public_minimum_from_renewal_buffer(): void {
		$policy = new OccurrenceProjectionWindowPolicy();

		self::assertTrue( $policy->supports_public_reads( '2027-01-01', '2028-01-01', '2027-01-01' ) );
		self::assertTrue( $policy->needs_renewal( '2027-01-01', '2028-01-01', '2027-01-01' ) );
		self::assertFalse( $policy->needs_renewal( '2027-01-01', '2028-03-26', '2027-01-01' ) );
	}

	/** Future, malformed and short windows fail closed. */
	public function test_rejects_invalid_or_insufficient_coverage(): void {
		$policy = new OccurrenceProjectionWindowPolicy();

		self::assertFalse( $policy->supports_public_reads( '2027-01-02', '2028-06-01', '2027-01-01' ) );
		self::assertFalse( $policy->supports_public_reads( 'invalid', '2028-06-01', '2027-01-01' ) );
		self::assertFalse( $policy->supports_public_reads( '2027-01-01', '2027-12-31', '2027-01-01' ) );
		self::assertTrue( $policy->needs_renewal( '2027-01-01', 'invalid', '2027-01-01' ) );
	}

	/** Invalid current dates cannot create a query or production window. */
	public function test_invalid_today_throws(): void {
		$this->expectException( InvalidArgumentException::class );

		( new OccurrenceProjectionWindowPolicy() )->fresh_window( '2027-02-29' );
	}
}
