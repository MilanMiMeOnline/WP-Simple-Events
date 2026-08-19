<?php
/**
 * Tests for native event collection rendering.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\EventArchiveRenderer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Query;

/**
 * Freezes taxonomy-specific title and controls behaviour.
 */
#[CoversClass( EventArchiveRenderer::class )]
final class EventArchiveRendererTest extends TestCase {
	/**
	 * Reset deterministic WordPress state between tests.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * A fixed term archive keeps its own title and cannot escape its term via filters.
	 */
	public function test_taxonomy_archive_uses_term_title_and_omits_cross_archive_filters(): void {
		WordPressState::set_archive_title( 'Event Category: Concerts' );
		$query = new WP_Query(
			array(
				'wpse_test_request' => 'taxonomy',
				'taxonomy'          => EventTaxonomies::CATEGORY,
				'wpse_period'       => 'all',
			)
		);

		$output = ( new EventArchiveRenderer() )->render( $query );

		self::assertStringContainsString( 'Event Category: Concerts', $output );
		self::assertStringContainsString( 'wpse-event-archive', $output );
		self::assertStringNotContainsString( 'wpse-event-archive-filters', $output );
	}
}
