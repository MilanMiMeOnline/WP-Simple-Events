<?php
/**
 * Tests for translating Elementor controls into native render attributes.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Elementor\WidgetSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( WidgetSettings::class )]
/**
 * Verifies that stored Elementor data is allowlisted before core rendering.
 */
final class ElementorWidgetSettingsTest extends TestCase {
	/**
	 * A complete list configuration maps to the existing shortcode contract.
	 */
	public function test_event_list_settings_are_normalized(): void {
		$attributes = WidgetSettings::event_list(
			array(
				'view'           => 'list',
				'period'         => 'past',
				'limit'          => '24',
				'columns'        => '4',
				'category'       => array( 'Workshops', '<script>', 'Workshops' ),
				'tag'            => array( 'Featured', array( 'invalid' ) ),
				'filters'        => 'yes',
				'pagination'     => '',
				'show_excerpt'   => '',
				'show_image'     => 'yes',
				'show_location'  => 'yes',
				'show_title'     => '',
				'show_date'      => 'yes',
				'excerpt_length' => '18',
				'heading_level'  => 'h4',
			)
		);

		self::assertSame(
			array(
				'view'                  => 'list',
				'period'                => 'past',
				'limit'                 => 24,
				'columns'               => 4,
				'category'              => array( 'workshops', 'script' ),
				'tag'                   => array( 'featured' ),
				'filters'               => true,
				'pagination'            => false,
				'show_excerpt'          => false,
				'show_image'            => true,
				'show_location'         => true,
				'show_title'            => false,
				'show_date'             => true,
				'excerpt_length'        => 18,
				'heading_level'         => 'h4',
				'filter_categories'     => true,
				'filter_tags'           => true,
				'filter_layout'         => 'auto',
				'filter_disclosure'     => 'auto',
				'filter_chips'          => true,
				'filter_results'        => false,
				'filter_label'          => '',
				'filter_period_label'   => '',
				'filter_category_label' => '',
				'filter_tag_label'      => '',
				'filter_apply_label'    => '',
			),
			$attributes
		);
	}

	/**
	 * Invalid list values fall back to the public contract defaults.
	 */
	public function test_event_list_settings_have_bounded_defaults(): void {
		$attributes = WidgetSettings::event_list(
			array(
				'view'    => 'carousel',
				'period'  => 'tomorrow',
				'limit'   => 1000,
				'columns' => 0,
			)
		);

		self::assertSame( 'grid', $attributes['view'] );
		self::assertSame( 'upcoming', $attributes['period'] );
		self::assertSame( 12, $attributes['limit'] );
		self::assertSame( 3, $attributes['columns'] );
		self::assertFalse( $attributes['filters'] );
		self::assertTrue( $attributes['pagination'] );
	}

	/**
	 * Calendar controls use the calendar shortcode's exact allowlist.
	 */
	public function test_calendar_settings_are_normalized(): void {
		self::assertSame(
			array(
				'initial_view'           => 'list',
				'mobile_view'            => 'month',
				'category'               => array( 'music' ),
				'tag'                    => array( 'free' ),
				'filters'                => false,
				'initial_date'           => '2028-02-29',
				'show_navigation'        => false,
				'show_today'             => true,
				'show_view_switcher'     => false,
				'fallback_heading_level' => 'h2',
				'filter_categories'      => true,
				'filter_tags'            => true,
				'filter_layout'          => 'auto',
				'filter_disclosure'      => 'auto',
				'filter_chips'           => true,
				'filter_results'         => true,
				'filter_label'           => '',
				'filter_period_label'    => '',
				'filter_category_label'  => '',
				'filter_tag_label'       => '',
				'filter_apply_label'     => '',
			),
			WidgetSettings::calendar(
				array(
					'initial_view'           => 'list',
					'mobile_view'            => 'month',
					'category'               => array( 'Music' ),
					'tag'                    => array( 'Free' ),
					'filters'                => '',
					'initial_date'           => '2028-02-29',
					'show_navigation'        => '',
					'show_today'             => 'yes',
					'show_view_switcher'     => '',
					'fallback_heading_level' => 'h2',
				)
			)
		);
	}

	/**
	 * Invalid calendar values do not reach the shortcode adapter.
	 */
	public function test_calendar_settings_have_safe_defaults(): void {
		$attributes = WidgetSettings::calendar(
			array(
				'initial_view' => 'agenda',
				'mobile_view'  => array( 'list' ),
			)
		);

		self::assertSame( 'month', $attributes['initial_view'] );
		self::assertSame( 'list', $attributes['mobile_view'] );
		self::assertTrue( $attributes['filters'] );
		self::assertSame( '', $attributes['initial_date'] );
		self::assertTrue( $attributes['show_navigation'] );
		self::assertTrue( $attributes['show_today'] );
		self::assertTrue( $attributes['show_view_switcher'] );
		self::assertSame( 'h3', $attributes['fallback_heading_level'] );
	}

	/**
	 * Details preview IDs must be positive decimal integers without coercion.
	 */
	public function test_details_settings_accept_only_a_valid_preview_event(): void {
		$valid = WidgetSettings::details( array( 'event_id' => '42' ) );

		self::assertSame( 42, $valid['id'] ?? null );
		self::assertTrue( $valid['show_title'] ?? false );
		self::assertSame( 'h1', $valid['heading_level'] ?? null );
		self::assertArrayNotHasKey( 'id', WidgetSettings::details( array( 'event_id' => '42x' ) ) );
		self::assertArrayNotHasKey( 'id', WidgetSettings::details( array( 'event_id' => 0 ) ) );
		self::assertArrayNotHasKey( 'id', WidgetSettings::details( array() ) );
	}
}
