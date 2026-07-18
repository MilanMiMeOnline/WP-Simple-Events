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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Freezes the block-theme fallback contract without booting WordPress.
 */
#[CoversClass( BlockTemplates::class )]
final class BlockTemplatesTest extends TestCase {
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
}
