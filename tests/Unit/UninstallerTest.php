<?php
/**
 * Tests for uninstall retention orchestration.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Lifecycle\Uninstaller;
use MiMe\WPSimpleEvents\Lifecycle\UninstallSettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( Uninstaller::class )]
/**
 * Verifies the fail-safe opt-in boundary before cleanup starts.
 */
final class UninstallerTest extends TestCase {
	/**
	 * Reset deterministic WordPress storage.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Default uninstall preserves event data and plugin options.
	 */
	public function test_preserves_everything_without_explicit_opt_in(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 30,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);

		( new Uninstaller() )->run();

		self::assertInstanceOf( WP_Post::class, WordPressState::post( 30 ) );
		self::assertSame( array(), WordPressState::deleted_post_ids() );
	}

	/**
	 * Explicit opt-in delegates to the destructive site cleanup.
	 */
	public function test_cleans_current_site_after_explicit_opt_in(): void {
		WordPressState::set_option( UninstallSettings::OPTION, true );
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 31,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);

		( new Uninstaller() )->run();

		self::assertSame( array( 31 ), WordPressState::deleted_post_ids() );
		self::assertFalse( WordPressState::has_option( UninstallSettings::OPTION ) );
	}

	/**
	 * Multisite cleanup visits every batch and respects each site's opt-in.
	 */
	public function test_multisite_cleanup_is_batched_and_site_specific(): void {
		$site_ids = range( 1, 101 );
		WordPressState::configure_multisite( $site_ids );
		WordPressState::set_site_option( 1, UninstallSettings::OPTION, false );
		WordPressState::set_site_option( 101, UninstallSettings::OPTION, true );

		( new Uninstaller() )->run();

		self::assertSame( $site_ids, WordPressState::switched_site_ids() );
		self::assertTrue( WordPressState::site_has_option( 1, UninstallSettings::OPTION ) );
		self::assertFalse( WordPressState::site_has_option( 101, UninstallSettings::OPTION ) );
		self::assertSame( 1, WordPressState::current_site_id() );
	}
}
