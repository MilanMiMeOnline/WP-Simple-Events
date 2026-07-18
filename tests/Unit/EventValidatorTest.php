<?php
/**
 * Tests for central event validation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\EventInput;
use MiMe\WPSimpleEvents\Application\EventValidationError;
use MiMe\WPSimpleEvents\Application\EventValidator;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies publication completeness, sanitization and date-range rules together.
 */
#[CoversClass( EventValidator::class )]
final class EventValidatorTest extends TestCase {
	/**
	 * Empty date fields are allowed only for incomplete drafts.
	 */
	public function test_empty_draft_is_valid_but_empty_publication_is_not(): void {
		$validator = new EventValidator();
		$input     = $this->valid_input(
			array(
				'start_date' => '',
				'start_time' => '',
				'end_date'   => '',
				'end_time'   => '',
			)
		);

		$draft = $validator->validate( $input, false );

		self::assertTrue( $draft->is_valid() );
		self::assertNull( $draft->data()?->date_range );

		$publication = $validator->validate( $input, true );

		self::assertFalse( $publication->is_valid() );
		self::assertSame( array( EventValidationError::MISSING_START_DATE ), $publication->errors() );
	}

	/**
	 * Timed values are canonicalized and receive UTC query indexes.
	 */
	public function test_timed_event_is_canonicalized(): void {
		$result = ( new EventValidator() )->validate( $this->valid_input(), true );
		$range  = $result->data()?->date_range;

		self::assertTrue( $result->is_valid() );
		self::assertNotNull( $range );
		self::assertSame( '2026-07-20T09:30:00', $range->start_local() );
		self::assertSame( '2026-07-20T11:00:00', $range->end_local() );
		self::assertSame( 1784532600, $range->start_utc() );
		self::assertSame( 1784538000, $range->end_utc() );
	}

	/**
	 * All-day values ignore stale time controls and use an inclusive end date.
	 */
	public function test_all_day_event_uses_inclusive_dates(): void {
		$result = ( new EventValidator() )->validate(
			$this->valid_input(
				array(
					'all_day'    => true,
					'end_date'   => '2026-07-22',
					'start_time' => '99:99',
					'end_time'   => '99:99',
				)
			),
			true
		);
		$range  = $result->data()?->date_range;

		self::assertTrue( $result->is_valid() );
		self::assertNotNull( $range );
		self::assertSame( '2026-07-20', $range->start_local() );
		self::assertSame( '2026-07-22', $range->end_local() );
		self::assertSame( 1784757599, $range->end_utc() );
	}

	/**
	 * A timed end must be entirely present or entirely absent.
	 */
	public function test_partial_timed_end_is_rejected(): void {
		$result = ( new EventValidator() )->validate(
			$this->valid_input( array( 'end_time' => '' ) ),
			false
		);

		self::assertSame( array( EventValidationError::INCOMPLETE_END ), $result->errors() );
	}

	/**
	 * End-before-start and DST-transition ambiguity are rejected by the range.
	 */
	public function test_invalid_chronology_and_ambiguous_time_are_rejected(): void {
		$validator = new EventValidator();
		$reversed  = $validator->validate(
			$this->valid_input(
				array(
					'end_date' => '2026-07-19',
				)
			),
			true
		);
		$ambiguous = $validator->validate(
			$this->valid_input(
				array(
					'start_date' => '2026-10-25',
					'start_time' => '02:30',
					'end_date'   => '',
					'end_time'   => '',
				)
			),
			true
		);

		self::assertSame( array( EventValidationError::INVALID_DATE_RANGE ), $reversed->errors() );
		self::assertSame( array( EventValidationError::INVALID_DATE_RANGE ), $ambiguous->errors() );
	}

	/**
	 * Unsafe URLs, status values and timezones remain explicit errors.
	 */
	public function test_invalid_non_date_fields_are_not_silently_replaced(): void {
		$result = ( new EventValidator() )->validate(
			$this->valid_input(
				array(
					'timezone'     => '../../etc/passwd',
					'location_url' => 'javascript:alert(1)',
					'event_url'    => 'mailto:test@example.com',
					'status'       => 'published',
				)
			),
			true
		);

		self::assertSame(
			array(
				EventValidationError::INVALID_TIMEZONE,
				EventValidationError::INVALID_STATUS,
				EventValidationError::INVALID_LOCATION_URL,
				EventValidationError::INVALID_EVENT_URL,
			),
			$result->errors()
		);
	}

	/**
	 * Text values are sanitized only after the complete record is accepted.
	 */
	public function test_valid_optional_fields_are_sanitized(): void {
		$result = ( new EventValidator() )->validate(
			$this->valid_input(
				array(
					'venue'   => '<b>Town Hall</b>',
					'address' => "Main <script>bad</script> Street\nBrussels",
				)
			),
			true
		);

		self::assertSame( 'Town Hall', $result->data()?->venue );
		self::assertSame( "Main bad Street\nBrussels", $result->data()?->address );
		self::assertSame( EventStatus::SCHEDULED, $result->data()?->status );
	}

	/**
	 * Build a complete valid input with selected overrides.
	 *
	 * @param array<string, bool|string> $overrides Selected property overrides.
	 */
	private function valid_input( array $overrides = array() ): EventInput {
		$values = array_merge(
			array(
				'start_date'   => '2026-07-20',
				'start_time'   => '09:30',
				'end_date'     => '2026-07-20',
				'end_time'     => '11:00',
				'all_day'      => false,
				'timezone'     => 'Europe/Brussels',
				'venue'        => 'Town Hall',
				'address'      => 'Main Street 1',
				'location_url' => 'https://example.com/location',
				'event_url'    => 'https://example.com/event',
				'status'       => EventStatus::SCHEDULED->value,
			),
			$overrides
		);

		return new EventInput(
			(string) $values['start_date'],
			(string) $values['start_time'],
			(string) $values['end_date'],
			(string) $values['end_time'],
			(bool) $values['all_day'],
			(string) $values['timezone'],
			(string) $values['venue'],
			(string) $values['address'],
			(string) $values['location_url'],
			(string) $values['event_url'],
			(string) $values['status']
		);
	}
}
