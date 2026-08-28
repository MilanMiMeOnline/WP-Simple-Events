<?php
/**
 * Tests for public calendar shortcode attributes.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\CalendarView;
use MiMe\WPSimpleEvents\Domain\CalendarLegendVisibility;
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
		self::assertSame( '', $attributes->initial_date );
		self::assertTrue( $attributes->show_navigation );
		self::assertTrue( $attributes->show_today );
		self::assertTrue( $attributes->show_view_switcher );
		self::assertSame( 'h3', $attributes->fallback_heading_level );
	}

	/**
	 * Unknown views and malformed booleans fall back safely.
	 */
	public function test_invalid_attributes_use_safe_defaults(): void {
		$attributes = CalendarShortcodeAttributes::from_shortcode(
			array(
				'initial_view'           => 'resourceTimeline',
				'mobile_view'            => 'agenda',
				'filters'                => 'maybe',
				'category'               => ' Workshops, Talks, workshops ',
				'initial_date'           => '2026-02-30',
				'show_navigation'        => 'false',
				'show_today'             => '0',
				'show_view_switcher'     => 'off',
				'fallback_heading_level' => 'h9',
			)
		);

		self::assertSame( CalendarView::MONTH, $attributes->initial_view );
		self::assertSame( CalendarView::LIST, $attributes->mobile_view );
		self::assertTrue( $attributes->filters );
		self::assertSame( array( 'workshops', 'talks' ), $attributes->category_slugs );
		self::assertSame( '', $attributes->initial_date );
		self::assertFalse( $attributes->show_navigation );
		self::assertFalse( $attributes->show_today );
		self::assertFalse( $attributes->show_view_switcher );
		self::assertSame( CalendarLegendVisibility::AUTO, $attributes->legend_visibility );
		self::assertSame( 'h3', $attributes->fallback_heading_level );
	}

	/** A real canonical date and safe fallback heading are retained. */
	public function test_calendar_presentation_options_are_allowlisted(): void {
		$attributes = CalendarShortcodeAttributes::from_shortcode(
			array(
				'initial_date'           => '2028-02-29',
				'fallback_heading_level' => 'h2',
				'legend'                 => 'show',
			)
		);

		self::assertSame( '2028-02-29', $attributes->initial_date );
		self::assertSame( 'h2', $attributes->fallback_heading_level );
		self::assertSame( CalendarLegendVisibility::SHOW, $attributes->legend_visibility );
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

	/**
	 * Disabled visitor filters cannot override configured initial constraints.
	 */
	public function test_disabled_filters_ignore_matching_request_values(): void {
		$attributes = CalendarShortcodeAttributes::from_shortcode(
			array(
				'category' => 'workshops',
				'filters'  => 'false',
			)
		)->with_request(
			array( 'wpse_calendar_1_category' => array( 'talks' ) ),
			'wpse_calendar_1'
		);

		self::assertFalse( $attributes->filters );
		self::assertSame( array( 'workshops' ), $attributes->category_slugs );
	}

	/** A hidden group remains a fixed builder constraint while visible groups filter. */
	public function test_hidden_filter_group_ignores_matching_request_values(): void {
		$attributes = CalendarShortcodeAttributes::from_shortcode(
			array(
				'category'          => 'members',
				'tag'               => 'featured',
				'filter_categories' => 'false',
			)
		)->with_request(
			array(
				'wpse_calendar_1_apply'    => '1',
				'wpse_calendar_1_category' => array( 'public' ),
				'wpse_calendar_1_tag'      => array( 'changed' ),
			),
			'wpse_calendar_1'
		);

		self::assertSame( array( 'members' ), $attributes->category_slugs );
		self::assertSame( array( 'changed' ), $attributes->tag_slugs );
	}
}
