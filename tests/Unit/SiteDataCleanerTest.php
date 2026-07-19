<?php
/**
 * Tests for explicit per-site plugin data cleanup.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Access\RoleManager;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\EventTimezoneDisplaySettings;
use MiMe\WPSimpleEvents\Lifecycle\Installer;
use MiMe\WPSimpleEvents\Lifecycle\SiteDataCleaner;
use MiMe\WPSimpleEvents\Lifecycle\UninstallSettings;
use MiMe\WPSimpleEvents\Seo\StructuredDataSettings;
use MiMe\WPSimpleEvents\Routing\EventArchiveRewriteManager;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( SiteDataCleaner::class )]
/**
 * Verifies bounded allowlisted cleanup and failure retention.
 */
final class SiteDataCleanerTest extends TestCase {
	/**
	 * Reset deterministic WordPress storage.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Explicit cleanup removes plugin-owned content but preserves shared media and posts.
	 */
	public function test_deletes_only_plugin_owned_site_data(): void {
		$administrator = WordPressState::add_role( 'administrator' );
		$editor        = WordPressState::add_role( 'editor' );
		( new RoleManager() )->grant();

		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 20,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 21,
					'post_type' => 'attachment',
				)
			)
		);
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 22,
					'post_type' => 'post',
				)
			)
		);
		WordPressState::set_taxonomy_terms( EventTaxonomies::CATEGORY, array( 3, 4 ) );
		WordPressState::set_taxonomy_terms( EventTaxonomies::TAG, array( 7 ) );
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( StructuredDataSettings::OPTION, false );
		WordPressState::set_option( EventTimezoneDisplaySettings::OPTION, true );
		WordPressState::set_option( EventArchiveSettings::SLUG_OPTION, 'calendar' );
		WordPressState::set_option( EventArchiveSettings::PER_PAGE_OPTION, 24 );
		WordPressState::set_option( EventArchiveSettings::DEFAULT_PERIOD_OPTION, 'all' );
		WordPressState::set_option( EventArchiveRewriteManager::PENDING_OPTION, 'calendar' );
		WordPressState::set_option( UninstallSettings::OPTION, true );

		$result = ( new SiteDataCleaner() )->clean();

		self::assertTrue( $result );
		self::assertSame( array( 20 ), WordPressState::deleted_post_ids() );
		self::assertInstanceOf( WP_Post::class, WordPressState::post( 21 ) );
		self::assertInstanceOf( WP_Post::class, WordPressState::post( 22 ) );
		self::assertSame( array( 3, 4 ), WordPressState::deleted_terms( EventTaxonomies::CATEGORY ) );
		self::assertSame( array( 7 ), WordPressState::deleted_terms( EventTaxonomies::TAG ) );
		self::assertFalse( WordPressState::has_option( Installer::VERSION_OPTION ) );
		self::assertFalse( WordPressState::has_option( StructuredDataSettings::OPTION ) );
		self::assertFalse( WordPressState::has_option( EventTimezoneDisplaySettings::OPTION ) );
		self::assertFalse( WordPressState::has_option( EventArchiveSettings::SLUG_OPTION ) );
		self::assertFalse( WordPressState::has_option( EventArchiveSettings::PER_PAGE_OPTION ) );
		self::assertFalse( WordPressState::has_option( EventArchiveSettings::DEFAULT_PERIOD_OPTION ) );
		self::assertFalse( WordPressState::has_option( EventArchiveRewriteManager::PENDING_OPTION ) );
		self::assertFalse( WordPressState::has_option( UninstallSettings::OPTION ) );
		self::assertSame( array(), $administrator->capabilities() );
		self::assertSame( array(), $editor->capabilities() );
	}

	/**
	 * An interrupted cleanup retains settings so incomplete deletion is not hidden.
	 */
	public function test_retains_options_when_taxonomy_cleanup_fails(): void {
		WordPressState::set_option( Installer::VERSION_OPTION, Installer::SCHEMA_VERSION );
		WordPressState::set_option( UninstallSettings::OPTION, true );
		WordPressState::fail_term_operations( true );

		$result = ( new SiteDataCleaner() )->clean();

		self::assertFalse( $result );
		self::assertTrue( WordPressState::has_option( Installer::VERSION_OPTION ) );
		self::assertTrue( WordPressState::has_option( UninstallSettings::OPTION ) );
	}
}
