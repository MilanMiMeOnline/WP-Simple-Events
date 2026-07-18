<?php
/**
 * Tests for bounded native event archive settings.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventArchiveSettings::class )]
/**
 * Verifies fail-safe defaults and strict setting boundaries.
 */
final class EventArchiveSettingsTest extends TestCase {
	/**
	 * Reset deterministic option storage.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Unconfigured archives retain the documented behaviour.
	 */
	public function test_resolves_documented_defaults(): void {
		$settings = new EventArchiveSettings();

		self::assertSame( 'events', $settings->slug() );
		self::assertSame( 10, $settings->per_page() );
		self::assertSame( EventPeriod::UPCOMING, $settings->default_period() );
	}

	/**
	 * Archive slugs are normalized to one bounded URL segment.
	 */
	public function test_sanitizes_one_safe_archive_path_segment(): void {
		$settings = new EventArchiveSettings();

		self::assertSame( 'community-events', $settings->sanitize_slug( ' Community Events ' ) );
		self::assertSame( 'event-list', $settings->sanitize_slug( '<b>Event List</b>' ) );
		self::assertSame( 'events', $settings->sanitize_slug( '' ) );
		self::assertSame( 'events', $settings->sanitize_slug( array( 'events' ) ) );
		self::assertSame( 'events', $settings->sanitize_slug( str_repeat( 'a', 201 ) ) );
	}

	/**
	 * Page size accepts only integers inside the public query boundary.
	 */
	public function test_bounds_archive_page_size(): void {
		$settings = new EventArchiveSettings();

		self::assertSame( 12, $settings->sanitize_per_page( '12' ) );
		self::assertSame( 1, $settings->sanitize_per_page( 1 ) );
		self::assertSame( 50, $settings->sanitize_per_page( 50 ) );
		self::assertSame( 10, $settings->sanitize_per_page( 0 ) );
		self::assertSame( 10, $settings->sanitize_per_page( 51 ) );
		self::assertSame( 10, $settings->sanitize_per_page( '2.5' ) );
		self::assertSame( 10, $settings->sanitize_per_page( true ) );
	}

	/**
	 * Existing sites inherit their bounded global page size until overridden.
	 */
	public function test_uses_the_bounded_site_page_size_until_an_archive_override_exists(): void {
		WordPressState::set_option( 'posts_per_page', 24 );
		$settings = new EventArchiveSettings();

		self::assertSame( 24, $settings->per_page() );
	}

	/**
	 * A site default cannot silently become a past-only archive.
	 */
	public function test_allows_only_upcoming_or_all_as_archive_default(): void {
		$settings = new EventArchiveSettings();

		self::assertSame( 'upcoming', $settings->sanitize_default_period( 'upcoming' ) );
		self::assertSame( 'all', $settings->sanitize_default_period( 'all' ) );
		self::assertSame( 'upcoming', $settings->sanitize_default_period( 'past' ) );
		self::assertSame( 'upcoming', $settings->sanitize_default_period( array( 'all' ) ) );
	}

	/**
	 * Stored options remain untrusted at their read boundary.
	 */
	public function test_revalidates_untrusted_stored_values(): void {
		WordPressState::set_option( EventArchiveSettings::SLUG_OPTION, '../Events' );
		WordPressState::set_option( EventArchiveSettings::PER_PAGE_OPTION, '999' );
		WordPressState::set_option( EventArchiveSettings::DEFAULT_PERIOD_OPTION, 'past' );
		$settings = new EventArchiveSettings();

		self::assertSame( 'events', $settings->slug() );
		self::assertSame( 10, $settings->per_page() );
		self::assertSame( EventPeriod::UPCOMING, $settings->default_period() );
	}
}
