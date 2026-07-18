<?php
/**
 * Tests for event structured-data construction.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Seo\EventSchemaBuilder;
use MiMe\WPSimpleEvents\Seo\EventSchemaInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventSchemaBuilder::class )]
#[CoversClass( EventSchemaInput::class )]
/**
 * Verifies the public schema contract without WordPress global state.
 */
final class EventSchemaBuilderTest extends TestCase {
	/**
	 * Timed events retain their explicit UTC offset and optional public data.
	 */
	public function test_builds_complete_timed_event_schema(): void {
		$schema = ( new EventSchemaBuilder() )->build(
			new EventSchemaInput(
				name: 'Summer concert',
				start_date: '2026-07-16T19:30:00+02:00',
				end_date: '2026-07-16T21:30:00+02:00',
				status: EventStatus::CANCELLED,
				url: 'https://example.com/events/summer-concert/',
				description: 'An outdoor concert.',
				image_url: 'https://example.com/concert.jpg',
				venue: 'Town Hall',
				address: 'Main Street 1, Brussels'
			)
		);

		self::assertSame( 'https://schema.org', $schema['@context'] ?? null );
		self::assertSame( 'Event', $schema['@type'] ?? null );
		self::assertSame( 'Summer concert', $schema['name'] ?? null );
		self::assertSame( '2026-07-16T19:30:00+02:00', $schema['startDate'] ?? null );
		self::assertSame( '2026-07-16T21:30:00+02:00', $schema['endDate'] ?? null );
		self::assertSame( 'https://schema.org/EventCancelled', $schema['eventStatus'] ?? null );
		self::assertSame( 'https://example.com/events/summer-concert/', $schema['url'] ?? null );
		self::assertSame( 'An outdoor concert.', $schema['description'] ?? null );
		self::assertSame( array( 'https://example.com/concert.jpg' ), $schema['image'] ?? null );
		self::assertSame(
			array(
				'@type'   => 'Place',
				'name'    => 'Town Hall',
				'address' => 'Main Street 1, Brussels',
			),
			$schema['location'] ?? null
		);
		self::assertArrayNotHasKey( 'offers', $schema );
	}

	/**
	 * All-day values remain dates and empty optional properties are omitted.
	 */
	public function test_builds_minimal_all_day_schema_without_inventing_data(): void {
		$schema = ( new EventSchemaBuilder() )->build(
			new EventSchemaInput(
				name: 'Community weekend',
				start_date: '2026-08-01',
				end_date: '2026-08-02',
				status: EventStatus::SCHEDULED,
				url: 'https://example.com/events/community-weekend/'
			)
		);

		self::assertSame( '2026-08-01', $schema['startDate'] ?? null );
		self::assertSame( '2026-08-02', $schema['endDate'] ?? null );
		self::assertSame( 'https://schema.org/EventScheduled', $schema['eventStatus'] ?? null );
		self::assertArrayNotHasKey( 'description', $schema );
		self::assertArrayNotHasKey( 'image', $schema );
		self::assertArrayNotHasKey( 'location', $schema );
	}

	/**
	 * Postponed is a public event status rather than a publication status.
	 */
	public function test_maps_postponed_status(): void {
		$schema = ( new EventSchemaBuilder() )->build(
			new EventSchemaInput(
				name: 'Workshop',
				start_date: '2026-09-10T09:00:00+02:00',
				end_date: '2026-09-10T12:00:00+02:00',
				status: EventStatus::POSTPONED,
				url: 'https://example.com/events/workshop/'
			)
		);

		self::assertSame( 'https://schema.org/EventPostponed', $schema['eventStatus'] ?? null );
	}

	/**
	 * Required public values cannot be silently replaced by guesses.
	 */
	public function test_rejects_missing_required_values(): void {
		$builder = new EventSchemaBuilder();

		self::assertNull(
			$builder->build(
				new EventSchemaInput(
					name: '',
					start_date: '2026-08-01',
					end_date: '2026-08-01',
					status: EventStatus::SCHEDULED,
					url: 'https://example.com/event/'
				)
			)
		);
		self::assertNull(
			$builder->build(
				new EventSchemaInput(
					name: 'Event',
					start_date: '',
					end_date: '',
					status: EventStatus::SCHEDULED,
					url: ''
				)
			)
		);
	}
}
