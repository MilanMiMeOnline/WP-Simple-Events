<?php
/**
 * Tests for validated event persistence.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\EventPersistence;
use MiMe\WPSimpleEvents\Application\ValidatedEventData;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies complete replacement and derived UTC metadata storage.
 */
#[CoversClass( EventPersistence::class )]
final class EventPersistenceTest extends TestCase {
	/**
	 * Reset metadata state before each test.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * A validated range writes canonical and derived values together.
	 */
	public function test_validated_range_is_persisted_completely(): void {
		WordPressState::update_post_meta( 42, EventMeta::DATES_NEED_REVIEW, true );
		$data = new ValidatedEventData(
			EventDateRange::from_local( '2026-07-20T09:30', '2026-07-20T11:00', false, 'Europe/Brussels' ),
			false,
			'Europe/Brussels',
			'Town Hall',
			'Main Street 1',
			'https://example.com/location',
			'https://example.com/event',
			'Register now',
			EventStatus::SCHEDULED
		);

		( new EventPersistence() )->persist( 42, $data );

		self::assertSame( '2026-07-20T09:30:00', WordPressState::post_meta( 42, EventMeta::START_LOCAL ) );
		self::assertSame( 1784532600, WordPressState::post_meta( 42, EventMeta::START_UTC ) );
		self::assertSame( 1784538000, WordPressState::post_meta( 42, EventMeta::END_UTC ) );
		self::assertSame( 'Town Hall', WordPressState::post_meta( 42, EventMeta::VENUE ) );
		self::assertSame( 'Register now', WordPressState::post_meta( 42, EventMeta::EVENT_URL_LABEL ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::DATES_NEED_REVIEW ) );
	}

	/**
	 * An incomplete valid draft removes stale dates and empty optional fields.
	 */
	public function test_incomplete_draft_deletes_stale_range_and_empty_values(): void {
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, 'stale' );
		WordPressState::update_post_meta( 42, EventMeta::START_UTC, 123 );
		WordPressState::update_post_meta( 42, EventMeta::VENUE, 'Old venue' );
		WordPressState::update_post_meta( 42, EventMeta::EVENT_URL_LABEL, 'Old label' );

		$data = new ValidatedEventData(
			null,
			false,
			'Europe/Brussels',
			'',
			'',
			'',
			'',
			'',
			EventStatus::POSTPONED
		);

		( new EventPersistence() )->persist( 42, $data );

		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::START_LOCAL ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::START_UTC ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::VENUE ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::EVENT_URL_LABEL ) );
		self::assertSame( EventStatus::POSTPONED->value, WordPressState::post_meta( 42, EventMeta::STATUS ) );
	}
}
