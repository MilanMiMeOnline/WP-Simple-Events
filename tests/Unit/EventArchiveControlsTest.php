<?php
/**
 * Tests for native event archive controls.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\EventArchiveControls;
use MiMe\WPSimpleEvents\Shortcode\EventListAttributes;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Term;

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
		self::assertStringContainsString( 'Clear all', $output );
		self::assertStringContainsString( 'href="https://example.com/events/"', $output );
		self::assertStringNotContainsString( 'wpse_period=', $output );
		self::assertStringNotContainsString( 'wpse_category=', $output );
		self::assertStringNotContainsString( 'wpse_tag=', $output );
	}

	/** The native archive shares the accessible taxonomy checkbox interaction. */
	public function test_category_filter_does_not_use_a_multiple_select(): void {
		WordPressState::set_taxonomy_terms(
			EventTaxonomies::CATEGORY,
			array(
				new WP_Term(
					array(
						'term_id'  => 4,
						'name'     => 'Workshops',
						'slug'     => 'workshops',
						'taxonomy' => EventTaxonomies::CATEGORY,
					)
				),
			)
		);

		$output = ( new EventArchiveControls() )->filters(
			EventListAttributes::from_shortcode( array( 'category' => 'workshops' ) )
		);

		self::assertStringContainsString( '<legend>Categories</legend>', $output );
		self::assertStringContainsString( 'type="checkbox"', $output );
		self::assertStringContainsString( 'name="wpse_category[]"', $output );
		self::assertStringNotContainsString( 'multiple', $output );
	}
}
