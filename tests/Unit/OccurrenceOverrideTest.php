<?php
/**
 * Tests for sparse occurrence overrides.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves the exact field allowlist, bounds and canonical sparse representation.
 */
#[CoversClass( OccurrenceOverride::class )]
final class OccurrenceOverrideTest extends TestCase {
	/**
	 * Every supported value type is retained under a stable key order.
	 */
	public function test_complete_supported_override_is_canonical(): void {
		$date_range = EventDateRange::from_local(
			'2027-06-10T20:00',
			'2027-06-10T22:00',
			false,
			'Europe/Brussels'
		);
		$override   = OccurrenceOverride::from_fields(
			'2027-06-10T19:00:00',
			array(
				OccurrenceOverride::VENUE             => '',
				OccurrenceOverride::TITLE             => 'Special edition',
				OccurrenceOverride::STATUS            => EventStatus::POSTPONED,
				OccurrenceOverride::NOTE              => "Doors open later.\nBring your ticket.",
				OccurrenceOverride::LOCATION_URL      => 'https://example.com/location',
				OccurrenceOverride::FEATURED_IMAGE_ID => 0,
				OccurrenceOverride::EVENT_URL_LABEL   => 'New registration page',
				OccurrenceOverride::EVENT_URL         => 'https://example.com/register',
				OccurrenceOverride::DATE_RANGE        => $date_range,
				OccurrenceOverride::ADDRESS           => "Main Street 1\nBrussels",
			)
		);

		self::assertSame(
			array(
				OccurrenceOverride::ADDRESS,
				OccurrenceOverride::DATE_RANGE,
				OccurrenceOverride::EVENT_URL,
				OccurrenceOverride::EVENT_URL_LABEL,
				OccurrenceOverride::FEATURED_IMAGE_ID,
				OccurrenceOverride::LOCATION_URL,
				OccurrenceOverride::NOTE,
				OccurrenceOverride::STATUS,
				OccurrenceOverride::TITLE,
				OccurrenceOverride::VENUE,
			),
			array_keys( $override->fields() )
		);
		self::assertSame( $date_range, $override->fields()[ OccurrenceOverride::DATE_RANGE ] );
		self::assertSame( '', $override->fields()[ OccurrenceOverride::VENUE ] );
	}

	/**
	 * Invalid identities, fields and weak scalar types fail completely.
	 *
	 * @param string $recurrence_id Candidate identity.
	 * @param array  $fields        Candidate field map.
	 */
	#[DataProvider( 'invalid_override_provider' )]
	public function test_invalid_override_is_rejected( string $recurrence_id, array $fields ): void {
		$this->expectException( InvalidArgumentException::class );

		OccurrenceOverride::from_fields( $recurrence_id, $fields );
	}

	/**
	 * Return representative malformed override values.
	 *
	 * @return iterable<string, array{0: string, 1: array<mixed>}>
	 */
	public static function invalid_override_provider(): iterable {
		yield 'one-off identity' => array( 'one-off', array( OccurrenceOverride::TITLE => 'Title' ) );
		yield 'invalid identity' => array( '../private', array( OccurrenceOverride::TITLE => 'Title' ) );
		yield 'empty fields' => array( '2027-01-01', array() );
		yield 'unknown field' => array( '2027-01-01', array( 'content' => 'Body' ) );
		yield 'empty title' => array( '2027-01-01', array( OccurrenceOverride::TITLE => '' ) );
		yield 'title with newline' => array( '2027-01-01', array( OccurrenceOverride::TITLE => "One\nTwo" ) );
		yield 'numeric image string' => array( '2027-01-01', array( OccurrenceOverride::FEATURED_IMAGE_ID => '2' ) );
		yield 'negative image' => array( '2027-01-01', array( OccurrenceOverride::FEATURED_IMAGE_ID => -1 ) );
		yield 'raw status' => array( '2027-01-01', array( OccurrenceOverride::STATUS => 'cancelled' ) );
		yield 'javascript URL' => array( '2027-01-01', array( OccurrenceOverride::EVENT_URL => 'javascript:alert(1)' ) );
		yield 'empty action label' => array( '2027-01-01', array( OccurrenceOverride::EVENT_URL_LABEL => '' ) );
		yield 'oversized note' => array( '2027-01-01', array( OccurrenceOverride::NOTE => str_repeat( 'a', 1_001 ) ) );
	}
}
