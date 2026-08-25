<?php
/**
 * Tests for the WordPress recurrence override input boundary.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Application\RecurrenceAggregateContentGuard;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves exact text, textarea and URL canonicalization before editor use.
 */
#[CoversClass( RecurrenceAggregateContentGuard::class )]
final class RecurrenceAggregateContentGuardTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';
	private const TARGET     = '2027-01-05T19:00:00';

	/** Canonical plain text, multiline text, empty inheritance masks and URLs pass. */
	public function test_canonical_override_content_is_accepted(): void {
		$guard = new RecurrenceAggregateContentGuard();

		$guard->assert_canonical(
			$this->aggregate(
				array(
					OccurrenceOverride::TITLE        => 'Late workshop',
					OccurrenceOverride::NOTE         => "Doors at 18:30.\nBring a ticket.",
					OccurrenceOverride::VENUE        => '',
					OccurrenceOverride::ADDRESS      => "Main Street 1\nBrussels",
					OccurrenceOverride::LOCATION_URL => 'https://example.com/location',
					OccurrenceOverride::EVENT_URL    => '',
				)
			)
		);

		self::addToAssertionCount( 1 );
	}

	/**
	 * Input that WordPress would strip or normalize is rejected, not rewritten.
	 *
	 * @param string $field Override field.
	 * @param string $value Noncanonical external value.
	 */
	#[DataProvider( 'noncanonical_content' )]
	public function test_noncanonical_override_content_is_rejected( string $field, string $value ): void {
		$this->expectException( InvalidArgumentException::class );

		( new RecurrenceAggregateContentGuard() )->assert_canonical(
			$this->aggregate( array( $field => $value ) )
		);
	}

	/**
	 * Return external strings that WordPress would rewrite.
	 *
	 * @return iterable<string, array{string, string}>
	 */
	public static function noncanonical_content(): iterable {
		yield 'HTML title' => array( OccurrenceOverride::TITLE, '<strong>Late</strong> workshop' );
		yield 'collapsed title whitespace' => array( OccurrenceOverride::TITLE, "Late\tworkshop" );
		yield 'HTML note' => array( OccurrenceOverride::NOTE, '<script>alert(1)</script>Doors open.' );
		yield 'noncanonical URL' => array( OccurrenceOverride::EVENT_URL, 'https://example.com/a b' );
	}

	/**
	 * Build one aggregate around the supplied sparse override fields.
	 *
	 * @param array $fields Override fields.
	 * @phpstan-param array<array-key, string> $fields
	 */
	private function aggregate( array $fields ): RecurrenceAggregate {
		$range = EventDateRange::from_local(
			'2027-01-04T19:00:00',
			'2027-01-04T21:00:00',
			false,
			'Europe/Brussels'
		);

		return RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $range->start_local(), $range, RecurrenceRule::daily() ) ),
			array(),
			array(),
			array( OccurrenceOverride::from_fields( self::TARGET, $fields ) )
		);
	}
}
