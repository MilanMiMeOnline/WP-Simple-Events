<?php
/**
 * Public event category legend tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventCategoryMeta;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\CalendarLegendVisibility;
use MiMe\WPSimpleEvents\Frontend\EventCategoryLegend;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Term;

#[CoversClass( EventCategoryLegend::class )]
/** Verifies accessible legend visibility and text-backed swatches. */
final class EventCategoryLegendTest extends TestCase {
	/** Configure one deterministic colored category. */
	protected function setUp(): void {
		WordPressState::reset();
		$term = new WP_Term(
			array(
				'term_id'  => 7,
				'name'     => 'Music',
				'slug'     => 'music',
				'taxonomy' => EventTaxonomies::CATEGORY,
			)
		);
		WordPressState::set_taxonomy_terms( EventTaxonomies::CATEGORY, array( $term ) );
		WordPressState::update_term_meta( 7, EventCategoryMeta::COLOR, '#336699' );
	}

	/** Auto avoids repeating an already visible category filter legend. */
	public function test_auto_avoids_duplicate_visible_category_filters(): void {
		self::assertSame( '', ( new EventCategoryLegend() )->render( CalendarLegendVisibility::AUTO, true ) );
	}

	/** Forced output retains visible text alongside the decorative swatch. */
	public function test_show_renders_swatch_and_visible_text(): void {
		$output = ( new EventCategoryLegend() )->render( CalendarLegendVisibility::SHOW, true );

		self::assertStringContainsString( '--wpse-category-color:#336699', $output );
		self::assertStringContainsString( '>Music</span>', $output );
	}
}
