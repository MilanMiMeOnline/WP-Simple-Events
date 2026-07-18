<?php
/**
 * Tests for event-details shortcode attributes.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Shortcode\EventDetailsAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the deliberately small event-details shortcode input contract.
 */
#[CoversClass( EventDetailsAttributes::class )]
final class EventDetailsAttributesTest extends TestCase {
	/**
	 * Omitting an ID selects the current event context later in the adapter.
	 */
	public function test_missing_id_uses_current_context(): void {
		$attributes = EventDetailsAttributes::from_shortcode( array() );

		self::assertNull( $attributes->event_id );
		self::assertFalse( $attributes->has_explicit_id );
	}

	/**
	 * A positive base-ten post ID is accepted without coercing other input.
	 */
	public function test_positive_numeric_id_is_accepted(): void {
		$attributes = EventDetailsAttributes::from_shortcode( array( 'id' => '42' ) );

		self::assertSame( 42, $attributes->event_id );
		self::assertTrue( $attributes->has_explicit_id );
	}

	/**
	 * Malformed, negative, zero and composite values cannot select a post.
	 */
	public function test_invalid_ids_are_rejected(): void {
		foreach ( array( '0', '-1', '1 OR 1=1', '4.2', array( '42' ), '<script>42</script>' ) as $value ) {
			$attributes = EventDetailsAttributes::from_shortcode( array( 'id' => $value ) );

			self::assertNull( $attributes->event_id );
			self::assertTrue( $attributes->has_explicit_id );
		}
	}
}
