<?php
/**
 * Tests for the event capability contract.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Access\EventCapabilities;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies least-privilege role and post-type mappings.
 */
#[CoversClass( EventCapabilities::class )]
final class EventCapabilitiesTest extends TestCase {
	/**
	 * WordPress meta capabilities are mapped but never granted directly.
	 */
	public function test_editorial_roles_receive_only_primitive_capabilities(): void {
		$granted = EventCapabilities::editorial();

		self::assertContains( EventCapabilities::PUBLISH_POSTS, $granted );
		self::assertContains( EventCapabilities::MANAGE_TERMS, $granted );
		self::assertNotContains( EventCapabilities::EDIT_POST, $granted );
		self::assertNotContains( EventCapabilities::DELETE_POST, $granted );
	}

	/**
	 * Only administrator and editor receive event rights automatically.
	 */
	public function test_shop_manager_is_not_an_automatic_editorial_role(): void {
		self::assertSame( array( 'administrator', 'editor' ), EventCapabilities::editorial_roles() );
		self::assertNotContains( 'shop_manager', EventCapabilities::editorial_roles() );
	}

	/**
	 * Creating events maps to the primitive plural edit capability.
	 */
	public function test_create_posts_uses_the_event_edit_capability(): void {
		self::assertSame(
			EventCapabilities::EDIT_POSTS,
			EventCapabilities::post_type_map()['create_posts']
		);
	}
}
