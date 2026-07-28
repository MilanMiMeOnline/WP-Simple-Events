<?php
/**
 * Tests for native block-template definitions.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\BlockTemplates;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Freezes the block-theme fallback contract without booting WordPress.
 */
#[CoversClass( BlockTemplates::class )]
final class BlockTemplatesTest extends TestCase {
	/**
	 * Reset deterministic WordPress state between tests.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Single and archive templates stay scoped to events and shared renderers.
	 */
	public function test_definitions_cover_single_and_archive_event_views(): void {
		$definitions = ( new BlockTemplates() )->definitions();

		self::assertSame(
			array( 'single-wpse_event', 'archive-wpse_event' ),
			array_keys( $definitions )
		);
		self::assertSame( array( EventPostType::POST_TYPE ), $definitions['single-wpse_event']['post_types'] );
		self::assertSame( array( EventPostType::POST_TYPE ), $definitions['archive-wpse_event']['post_types'] );
		self::assertStringContainsString( '<!-- wp:wpse/native-single /-->', $definitions['single-wpse_event']['content'] );
		self::assertStringContainsString( '<!-- wp:wpse/native-archive /-->', $definitions['archive-wpse_event']['content'] );
	}

	/**
	 * Shared render blocks remain available to classic and hybrid themes.
	 */
	public function test_register_skips_plugin_templates_outside_full_block_themes(): void {
		( new BlockTemplates() )->register();

		self::assertSame( array( 'wpse/native-single', 'wpse/native-archive' ), WordPressState::registered_block_types() );
		self::assertSame( array(), WordPressState::registered_block_templates() );
	}

	/**
	 * Full block themes receive both plugin-owned fallback templates.
	 */
	public function test_register_exposes_plugin_templates_to_full_block_themes(): void {
		WordPressState::set_block_theme( true );

		( new BlockTemplates() )->register();

		self::assertSame(
			array(
				'mime-simple-events-calendar//single-wpse_event',
				'mime-simple-events-calendar//archive-wpse_event',
			),
			WordPressState::registered_block_templates()
		);
	}
}
