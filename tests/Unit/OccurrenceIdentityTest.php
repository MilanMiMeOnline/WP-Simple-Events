<?php
/**
 * Tests for immutable occurrence identities.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic public keys and strict identity validation.
 */
#[CoversClass( OccurrenceIdentity::class )]
final class OccurrenceIdentityTest extends TestCase {
	private const SERIES_UID = 'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad';

	/**
	 * Moving an occurrence does not change the key derived from its original slot.
	 */
	public function test_public_key_is_stable_for_the_original_slot(): void {
		$first  = OccurrenceIdentity::from( self::SERIES_UID, '2026-09-04T20:00:00' );
		$second = OccurrenceIdentity::from( strtoupper( self::SERIES_UID ), '2026-09-04T20:00:00' );

		self::assertSame( $first->public_key(), $second->public_key() );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{32}$/D', $first->public_key() );
	}

	/**
	 * Different original slots cannot collapse into one occurrence URL.
	 */
	public function test_different_slots_have_different_public_keys(): void {
		$first  = OccurrenceIdentity::from( self::SERIES_UID, '2026-09-04T20:00:00' );
		$second = OccurrenceIdentity::from( self::SERIES_UID, '2026-09-11T20:00:00' );

		self::assertNotSame( $first->public_key(), $second->public_key() );
	}

	/**
	 * Manual additions retain their random identity independently of their date.
	 */
	public function test_manual_identity_is_supported(): void {
		$identity = OccurrenceIdentity::from(
			self::SERIES_UID,
			'manual:019c1d83-1798-4fac-a66d-ae8d67c46319'
		);

		self::assertSame( 'manual:019c1d83-1798-4fac-a66d-ae8d67c46319', $identity->recurrence_id() );
	}

	/**
	 * Aggregate helpers distinguish generated and manual identities explicitly.
	 */
	public function test_recurrence_identity_kinds_are_distinguished(): void {
		self::assertTrue( OccurrenceIdentity::is_generated_recurrence_id( '2027-01-01' ) );
		self::assertTrue( OccurrenceIdentity::is_generated_recurrence_id( '2027-01-01T19:00:00' ) );
		self::assertFalse( OccurrenceIdentity::is_generated_recurrence_id( 'one-off' ) );
		self::assertFalse( OccurrenceIdentity::is_generated_recurrence_id( 'manual:019c1d83-1798-4fac-a66d-ae8d67c46319' ) );
		self::assertTrue( OccurrenceIdentity::is_manual_recurrence_id( 'manual:019c1d83-1798-4fac-a66d-ae8d67c46319' ) );
		self::assertFalse( OccurrenceIdentity::is_manual_recurrence_id( '2027-01-01' ) );
	}

	/**
	 * A malformed series UUID is rejected at the domain boundary.
	 */
	public function test_invalid_series_uid_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		OccurrenceIdentity::from( 'not-a-uuid', 'one-off' );
	}

	/**
	 * Invalid calendar slots are rejected instead of being normalized by PHP.
	 */
	public function test_invalid_recurrence_slot_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		OccurrenceIdentity::from( self::SERIES_UID, '2026-02-30T20:00:00' );
	}
}
