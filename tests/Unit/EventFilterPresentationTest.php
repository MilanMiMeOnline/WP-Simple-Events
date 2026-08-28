<?php
/**
 * Tests for bounded event-filter presentation settings.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Frontend\EventFilterPresentation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventFilterPresentation::class )]
/** Protects compatible defaults and strict editor-facing presentation choices. */
final class EventFilterPresentationTest extends TestCase {
	/** Existing saved components retain the pre-FCR-4 presentation. */
	public function test_defaults_preserve_existing_components(): void {
		$list     = EventFilterPresentation::from_attributes( array(), false );
		$calendar = EventFilterPresentation::from_attributes( array(), true );

		self::assertTrue( $list->show_categories );
		self::assertTrue( $list->show_tags );
		self::assertSame( 'auto', $list->layout );
		self::assertSame( 'auto', $list->disclosure );
		self::assertTrue( $list->show_chips );
		self::assertFalse( $list->show_results );
		self::assertTrue( $calendar->show_results );
	}

	/** Unknown shapes and unsafe label markup cannot become public instructions. */
	public function test_values_are_allowlisted_and_labels_are_bounded_plain_text(): void {
		$presentation = EventFilterPresentation::from_attributes(
			array(
				'filter_categories'     => 'false',
				'filter_tags'           => array( 'false' ),
				'filter_layout'         => 'masonry',
				'filter_disclosure'     => 'closed',
				'filter_chips'          => 'off',
				'filter_results'        => 'yes',
				'filter_category_label' => '<strong>Topics</strong>',
				'filter_apply_label'    => str_repeat( 'x', 120 ),
			),
			false
		);

		self::assertFalse( $presentation->show_categories );
		self::assertTrue( $presentation->show_tags );
		self::assertSame( 'auto', $presentation->layout );
		self::assertSame( 'closed', $presentation->disclosure );
		self::assertFalse( $presentation->show_chips );
		self::assertTrue( $presentation->show_results );
		self::assertSame( 'Topics', $presentation->category_label );
		self::assertSame( 80, strlen( $presentation->apply_label ) );
	}
}
