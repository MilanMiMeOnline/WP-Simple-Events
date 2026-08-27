<?php
/**
 * Tests for native event archive controls.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Frontend\EventArchiveControls;
use MiMe\WPSimpleEvents\Shortcode\EventListAttributes;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Protects the native archive's no-JavaScript filter escape path.
 */
#[CoversClass( EventArchiveControls::class )]
final class EventArchiveControlsTest extends TestCase {
	/** Reset isolated WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** The native archive always exposes a clean route that removes event filters. */
	public function test_filter_form_exposes_a_clear_action(): void {
		$output = ( new EventArchiveControls() )->filters(
			EventListAttributes::from_shortcode(
				array(
					'period'   => 'past',
					'category' => 'workshops',
					'tag'      => 'family',
				)
			)
		);

		self::assertStringContainsString( 'Apply filters', $output );
		self::assertStringContainsString( 'Clear filters', $output );
		self::assertStringContainsString( 'href="https://example.com/events/"', $output );
		self::assertStringNotContainsString( 'wpse_period=', $output );
		self::assertStringNotContainsString( 'wpse_category=', $output );
		self::assertStringNotContainsString( 'wpse_tag=', $output );
	}
}
