<?php
/**
 * Tests for native WordPress content definitions.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Protects the public post-type, taxonomy and metadata contract.
 */
#[CoversClass( EventPostType::class )]
#[CoversClass( EventTaxonomies::class )]
#[CoversClass( EventMeta::class )]
final class ContentDefinitionTest extends TestCase {
	/**
	 * Reset deterministic options before every content contract test.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * The event post type remains public, REST-enabled and editor-compatible.
	 */
	public function test_event_post_type_contract(): void {
		$arguments = ( new EventPostType() )->arguments();

		self::assertTrue( $arguments['public'] );
		self::assertTrue( $arguments['show_in_rest'] );
		self::assertTrue( $arguments['map_meta_cap'] );
		self::assertSame( 'events', $arguments['has_archive'] );
		self::assertContains( 'custom-fields', $arguments['supports'] );
		self::assertContains( EventTaxonomies::CATEGORY, $arguments['taxonomies'] );
		self::assertContains( EventTaxonomies::TAG, $arguments['taxonomies'] );
	}

	/**
	 * Archive and single permalinks share the same validated configured base.
	 */
	public function test_event_post_type_uses_the_validated_configured_archive_slug(): void {
		WordPressState::set_option( EventArchiveSettings::SLUG_OPTION, 'community-events' );
		$arguments = ( new EventPostType() )->arguments();

		self::assertSame( 'community-events', $arguments['has_archive'] );
		self::assertSame( 'community-events', $arguments['rewrite']['slug'] );
	}

	/**
	 * Event categories and tags remain isolated and REST-enabled.
	 */
	public function test_event_taxonomy_contract(): void {
		$taxonomies = new EventTaxonomies();
		$category   = $taxonomies->category_arguments();
		$tag        = $taxonomies->tag_arguments();

		self::assertTrue( $category['hierarchical'] );
		self::assertFalse( $tag['hierarchical'] );
		self::assertTrue( $category['show_in_rest'] );
		self::assertTrue( $tag['show_in_rest'] );
		self::assertSame( 'event-category', $category['rewrite']['slug'] );
		self::assertSame( 'event-tag', $tag['rewrite']['slug'] );
	}

	/**
	 * Every agreed event field is typed, single and revisioned.
	 */
	public function test_registered_meta_contract(): void {
		$definitions = ( new EventMeta() )->definitions();
		$expected    = array(
			EventMeta::START_LOCAL,
			EventMeta::END_LOCAL,
			EventMeta::START_UTC,
			EventMeta::END_UTC,
			EventMeta::ALL_DAY,
			EventMeta::TIMEZONE,
			EventMeta::VENUE,
			EventMeta::ADDRESS,
			EventMeta::LOCATION_URL,
			EventMeta::EVENT_URL,
			EventMeta::STATUS,
			EventMeta::DATES_NEED_REVIEW,
		);

		self::assertSame( $expected, array_keys( $definitions ) );

		foreach ( $definitions as $definition ) {
			self::assertTrue( $definition['single'] );
			self::assertTrue( $definition['revisions_enabled'] );
			self::assertIsCallable( $definition['sanitize_callback'] );
			self::assertIsCallable( $definition['auth_callback'] );
		}

		self::assertFalse( $definitions[ EventMeta::START_UTC ]['show_in_rest'] );
		self::assertFalse( $definitions[ EventMeta::END_UTC ]['show_in_rest'] );
		self::assertFalse( $definitions[ EventMeta::DATES_NEED_REVIEW ]['show_in_rest'] );
		self::assertSame( EventStatus::SCHEDULED->value, $definitions[ EventMeta::STATUS ]['default'] );
		self::assertSame(
			EventStatus::values(),
			$definitions[ EventMeta::STATUS ]['show_in_rest']['schema']['enum']
		);
	}
}
