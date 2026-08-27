<?php
/**
 * Tests for bounded public event-filter URL state.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Frontend\EventFilterUrlState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Protects component isolation and the public query allowlist. */
#[CoversClass( EventFilterUrlState::class )]
final class EventFilterUrlStateTest extends TestCase {
	/** Other list and calendar instances survive while unknown input is rejected. */
	public function test_preserves_only_bounded_other_instance_state(): void {
		$state = new EventFilterUrlState();

		$preserved = $state->preserved(
			array(
				'wpse_1_category'          => array( 'current' ),
				'wpse_2_apply'             => '1',
				'wpse_2_category'          => array( 'talks', '<script>' ),
				'wpse_calendar_3_tag'      => array( 'family' ),
				'wpse_calendar_3_category' => array_fill( 0, 25, 'bounded' ),
				'redirect_to'              => 'https://attacker.example/',
			),
			'wpse_1'
		);

		self::assertArrayNotHasKey( 'wpse_1_category', $preserved );
		self::assertSame( '1', $preserved['wpse_2_apply'] );
		self::assertSame( array( 'talks', '' ), $preserved['wpse_2_category'] );
		self::assertSame( array( 'family' ), $preserved['wpse_calendar_3_tag'] );
		self::assertCount( 20, $preserved['wpse_calendar_3_category'] );
		self::assertArrayNotHasKey( 'redirect_to', $preserved );
	}

	/** Malformed numeric request keys are ignored instead of reaching string checks. */
	public function test_ignores_non_string_request_keys(): void {
		$state = new EventFilterUrlState();

		self::assertSame(
			array( 'wpse_2_apply' => '1' ),
			$state->preserved(
				array(
					0              => 'malformed',
					'wpse_2_apply' => '1',
				),
				'wpse_1'
			)
		);
	}

	/** URL and hidden-field output retain normalized array semantics. */
	public function test_builds_url_and_escaped_hidden_fields(): void {
		$state  = new EventFilterUrlState();
		$values = array(
			'wpse_2_apply'    => '1',
			'wpse_2_category' => array( 'talks', 'family' ),
		);
		$url    = $state->url( 'https://example.test/events/', $values );
		$fields = $state->hidden_fields( $values );

		self::assertStringContainsString( 'wpse_2_apply=1', $url );
		self::assertStringContainsString( 'wpse_2_category', $url );
		self::assertSame( 2, substr_count( $fields, 'name="wpse_2_category[]"' ) );
		self::assertStringContainsString( 'value="family"', $fields );
	}
}
