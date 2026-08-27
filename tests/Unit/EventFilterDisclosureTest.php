<?php
/**
 * Tests for progressive public filter disclosure markup.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Frontend\EventFilterDisclosure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Protects the visible no-JavaScript panel and inert enhancement trigger. */
#[CoversClass( EventFilterDisclosure::class )]
final class EventFilterDisclosureTest extends TestCase {
	/** The trigger starts inert while its controlled panel remains available. */
	public function test_renders_an_inert_trigger_and_visible_panel(): void {
		$output = ( new EventFilterDisclosure() )->render(
			'filters"><script>',
			'<p class="safe-field">Field</p>',
			2
		);

		self::assertStringContainsString( 'data-wpse-filter-toggle', $output );
		self::assertStringContainsString( 'hidden aria-expanded="true"', $output );
		self::assertStringContainsString( 'aria-controls="filters&quot;&gt;&lt;script&gt;"', $output );
		self::assertStringContainsString( 'data-wpse-filter-panel', $output );
		self::assertStringContainsString( '<p class="safe-field">Field</p>', $output );
		self::assertStringContainsString( '(2)', $output );
	}

	/** Invalid internal counts never produce a negative visitor label. */
	public function test_normalizes_a_negative_active_count(): void {
		$output = ( new EventFilterDisclosure() )->render( 'filters', '', -5 );

		self::assertStringContainsString( 'data-wpse-filter-count hidden', $output );
		self::assertStringContainsString( '(0)', $output );
		self::assertStringNotContainsString( '(-5)', $output );
	}
}
