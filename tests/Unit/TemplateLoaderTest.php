<?php
/**
 * Tests for event template discovery.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\TemplateLoader;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'WPSE_PLUGIN_DIR' ) ) {
	define( 'WPSE_PLUGIN_DIR', dirname( __DIR__, 2 ) );
}

/**
 * Freezes the classic, hybrid and full block-theme selection boundary.
 */
#[CoversClass( TemplateLoader::class )]
final class TemplateLoaderTest extends TestCase {
	/**
	 * Reset deterministic WordPress state between tests.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Unrelated requests preserve the template WordPress already selected.
	 */
	public function test_non_event_request_preserves_original_template(): void {
		self::assertSame( '/theme/page.php', ( new TemplateLoader() )->template( '/theme/page.php' ) );
	}

	/**
	 * Classic and hybrid themes retain PHP header and footer ownership.
	 */
	public function test_non_block_theme_uses_bundled_php_single_template(): void {
		WordPressState::set_singular_event( true );

		$result = ( new TemplateLoader() )->template( '/theme/single.php' );

		self::assertSame( WPSE_PLUGIN_DIR . '/templates/single-wpse_event.php', $result );
		self::assertSame( array(), WordPressState::block_template_calls() );
	}

	/**
	 * Event archives follow the same PHP ownership rule outside block themes.
	 */
	public function test_non_block_theme_uses_bundled_php_archive_template(): void {
		WordPressState::set_event_archive( true );

		$result = ( new TemplateLoader() )->template( '/theme/archive.php' );

		self::assertSame( WPSE_PLUGIN_DIR . '/templates/archive-wpse_event.php', $result );
		self::assertSame( array(), WordPressState::block_template_calls() );
	}

	/**
	 * Explicit PHP theme overrides always win, including in block themes.
	 */
	public function test_theme_php_override_wins_before_block_template_discovery(): void {
		WordPressState::set_singular_event( true );
		WordPressState::set_block_theme( true );
		WordPressState::set_theme_template( '/theme/mime-simple-events-calendar/single-wpse_event.php' );

		$result = ( new TemplateLoader() )->template( '/theme/single.php' );

		self::assertSame( '/theme/mime-simple-events-calendar/single-wpse_event.php', $result );
		self::assertSame( array(), WordPressState::block_template_calls() );
	}

	/**
	 * Full block themes use the correct WordPress hierarchy and canvas.
	 */
	public function test_full_block_theme_resolves_single_block_template(): void {
		WordPressState::set_singular_event( true );
		WordPressState::set_block_theme( true );
		WordPressState::set_block_template_result( '/wordpress/template-canvas.php' );

		$result = ( new TemplateLoader() )->template( '/theme/single.php' );

		self::assertSame( '/wordpress/template-canvas.php', $result );
		self::assertSame(
			array(
				array(
					'template'  => WPSE_PLUGIN_DIR . '/templates/single-wpse_event.php',
					'type'      => 'single-wpse_event',
					'templates' => array( 'single-wpse_event.php', 'index.php' ),
				),
			),
			WordPressState::block_template_calls()
		);
	}

	/**
	 * Classic event taxonomy archives use the shared event archive in the theme shell.
	 */
	public function test_classic_event_category_archive_uses_bundled_event_archive_template(): void {
		WordPressState::set_event_taxonomy_archive( EventTaxonomies::CATEGORY );

		$result = ( new TemplateLoader() )->template( '/theme/taxonomy.php' );

		self::assertSame( WPSE_PLUGIN_DIR . '/templates/archive-wpse_event.php', $result );
	}

	/**
	 * Full block themes resolve the registered taxonomy template hierarchy.
	 */
	public function test_full_block_theme_resolves_event_tag_taxonomy_template(): void {
		WordPressState::set_event_taxonomy_archive( EventTaxonomies::TAG );
		WordPressState::set_block_theme( true );
		WordPressState::set_block_template_result( '/wordpress/template-canvas.php' );

		$result = ( new TemplateLoader() )->template( '/theme/taxonomy.php' );

		self::assertSame( '/wordpress/template-canvas.php', $result );
		self::assertSame(
			array(
				array(
					'template'  => WPSE_PLUGIN_DIR . '/templates/archive-wpse_event.php',
					'type'      => 'taxonomy-wpse_event_tag',
					'templates' => array( 'taxonomy-wpse_event_tag.php', 'archive-wpse_event.php', 'taxonomy.php', 'archive.php', 'index.php' ),
				),
			),
			WordPressState::block_template_calls()
		);
	}
}
