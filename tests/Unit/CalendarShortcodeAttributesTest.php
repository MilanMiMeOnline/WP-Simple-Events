<?php
/**
 * Tests for public calendar shortcode attributes.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\CalendarView;
use MiMe\WPSimpleEvents\Shortcode\CalendarShortcodeAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies calendar view allowlists and per-instance filter isolation.
 */
#[CoversClass( CalendarShortcodeAttributes::class )]
final class CalendarShortcodeAttributesTest extends TestCase {
	/**
	 * Defaults provide a month view with a mobile list and visible filters.
	 */
	public function test_defaults_are_accessible_and_predictable(): void {
		$attributes = CalendarShortcodeAttributes::from_shortcode( array() );

		self::assertSame( CalendarView::MONTH, $attributes->initial_view );
		self::assertSame( CalendarView::LIST, $attributes->mobile_view );
		self::assertTrue( $attributes->filters );
		self::assertSame( array(), $attributes->category_slugs );
	}

	/**
	 * Unknown views and malformed booleans fall back safely.
	 */
	public function test_invalid_attributes_use_safe_defaults(): void {
		$attributes = CalendarShortcodeAttributes::from_shortcode(
			array(
				'initial_view' => 'resourceTimeline',
				'mobile_view'  => 'agenda',
				'filters'      => 'maybe',
				'category'     => ' Workshops, Talks, workshops ',
			)
		);

		self::assertSame( CalendarView::MONTH, $attributes->initial_view );
		self::assertSame( CalendarView::LIST, $attributes->mobile_view );
		self::assertTrue( $attributes->filters );
		self::assertSame( array( 'workshops', 'talks' ), $attributes->category_slugs );
	}

	/**
	 * Only the matching calendar instance can alter its selected terms.
	 */
	public function test_request_filters_are_namespaced_per_instance(): void {
		$attributes = CalendarShortcodeAttributes::from_shortcode( array() )->with_request(
			array(
				'wpse_calendar_1_category' => array( 'Talks' ),
				'wpse_calendar_2_category' => array( 'Workshops' ),
			),
			'wpse_calendar_1'
		);

		self::assertSame( array( 'talks' ), $attributes->category_slugs );
	}
}
