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
		self::assertTrue( $attributes->options->show_title );
		self::assertTrue( $attributes->options->show_location );
		self::assertSame( 'h1', $attributes->options->heading_level );
	}

	/** Composite presentation choices are bounded and plain-text labels are sanitized. */
	public function test_presentation_options_are_normalized(): void {
		$attributes = EventDetailsAttributes::from_shortcode(
			array(
				'show_title'    => 'false',
				'show_location' => '0',
				'show_terms'    => 'off',
				'heading_level' => 'h3',
				'date_label'    => " When\n<script> ",
				'action_label'  => str_repeat( 'A', 250 ),
			)
		);

		self::assertFalse( $attributes->options->show_title );
		self::assertFalse( $attributes->options->show_location );
		self::assertFalse( $attributes->options->show_terms );
		self::assertSame( 'h3', $attributes->options->heading_level );
		self::assertSame( 'When', $attributes->options->date_label );
		self::assertSame( 120, strlen( $attributes->options->action_label ) );
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
