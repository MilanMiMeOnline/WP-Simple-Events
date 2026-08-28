<?php
/**
 * Tests for event-category color metadata.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventCategoryMeta;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventCategoryMeta::class )]
/** Ensures taxonomy presentation data is strict, optional and permission-aware. */
final class EventCategoryMetaTest extends TestCase {
	/** Reset deterministic capability state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** The one registered field is private to plugin presentation adapters. */
	public function test_defines_one_strict_single_value_color(): void {
		$definitions = ( new EventCategoryMeta() )->definitions();
		$definition  = $definitions[ EventCategoryMeta::COLOR ] ?? null;

		self::assertIsArray( $definition );
		self::assertSame( 'string', $definition['type'] ?? null );
		self::assertTrue( $definition['single'] ?? false );
		self::assertFalse( $definition['show_in_rest'] ?? true );
		self::assertSame( '#aabbcc', ( $definition['sanitize_callback'] )( '#AABBCC' ) );
		self::assertSame( '', ( $definition['sanitize_callback'] )( 'red;display:none' ) );
	}

	/** Metadata mutation requires the event taxonomy management capability. */
	public function test_authorization_uses_the_current_event_taxonomy_capability(): void {
		$meta = new EventCategoryMeta();

		WordPressState::allow_current_user( false );
		self::assertFalse( $meta->authorize( true, EventCategoryMeta::COLOR, 8 ) );

		WordPressState::allow_current_user( true );
		self::assertTrue( $meta->authorize( false, EventCategoryMeta::COLOR, 8 ) );
	}
}
