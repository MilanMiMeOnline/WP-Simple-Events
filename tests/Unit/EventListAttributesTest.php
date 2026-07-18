<?php
/**
 * Tests for event list shortcode attributes.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\EventListView;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Shortcode\EventListAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies allowlists, bounds and per-instance request isolation.
 */
#[CoversClass( EventListAttributes::class )]
final class EventListAttributesTest extends TestCase {
	/**
	 * Defaults produce a useful bounded upcoming grid.
	 */
	public function test_defaults_are_bounded_and_predictable(): void {
		$attributes = EventListAttributes::from_shortcode( array() );

		self::assertSame( EventListView::GRID, $attributes->view );
		self::assertSame( EventPeriod::UPCOMING, $attributes->period );
		self::assertSame( 12, $attributes->limit );
		self::assertSame( 3, $attributes->columns );
		self::assertFalse( $attributes->filters );
		self::assertTrue( $attributes->pagination );
	}

	/**
	 * Invalid values fall back rather than becoming query instructions.
	 */
	public function test_invalid_attributes_use_safe_fallbacks(): void {
		$attributes = EventListAttributes::from_shortcode(
			array(
				'view'          => 'carousel',
				'period'        => 'private',
				'limit'         => '999999',
				'columns'       => '-2',
				'filters'       => 'maybe',
				'pagination'    => array( 'true' ),
				'show_excerpt'  => 'false',
				'show_image'    => '0',
				'show_location' => 'off',
			)
		);

		self::assertSame( EventListView::GRID, $attributes->view );
		self::assertSame( EventPeriod::UPCOMING, $attributes->period );
		self::assertSame( 12, $attributes->limit );
		self::assertSame( 3, $attributes->columns );
		self::assertFalse( $attributes->filters );
		self::assertTrue( $attributes->pagination );
		self::assertFalse( $attributes->show_excerpt );
		self::assertFalse( $attributes->show_image );
		self::assertFalse( $attributes->show_location );
	}

	/**
	 * Comma-separated slugs are normalized, deduplicated and bounded.
	 */
	public function test_term_slugs_are_normalized_and_deduplicated(): void {
		$attributes = EventListAttributes::from_shortcode(
			array(
				'category' => 'Workshops, Talks, workshops, <script>',
				'tag'      => 'Featured',
			)
		);

		self::assertSame( array( 'workshops', 'talks', 'script' ), $attributes->category_slugs );
		self::assertSame( array( 'featured' ), $attributes->tag_slugs );
	}

	/**
	 * Only the matching shortcode instance may alter its filters and page.
	 */
	public function test_request_parameters_are_namespaced_per_instance(): void {
		$attributes = EventListAttributes::from_shortcode(
			array(
				'filters' => 'true',
			)
		);
		$request    = array(
			'wpse_1_period'   => 'past',
			'wpse_1_category' => array( 'Talks' ),
			'wpse_1_page'     => '4',
			'wpse_2_period'   => 'all',
			'wpse_2_page'     => '9',
		);
		$instance   = $attributes->with_request( $request, 'wpse_1' );

		self::assertSame( EventPeriod::PAST, $instance->period );
		self::assertSame( array( 'talks' ), $instance->category_slugs );
		self::assertSame( 4, $instance->page );
	}

	/**
	 * Excessive or malformed page values use the first page.
	 */
	public function test_request_page_is_strictly_bounded(): void {
		$attributes = EventListAttributes::from_shortcode( array() );

		self::assertSame(
			1,
			$attributes->with_request(
				array( 'wpse_1_page' => (string) ( EventQueryCriteria::MAX_PAGE + 1 ) ),
				'wpse_1'
			)->page
		);
	}
}
