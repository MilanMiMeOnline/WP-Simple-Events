<?php
/**
 * Tests for strict filter design variables.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Frontend\EventFilterStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventFilterStyle::class )]
/** Ensures editor values cannot become arbitrary inline CSS. */
final class EventFilterStyleTest extends TestCase {
	/** Only allowlisted colors and bounded integer pixels become declarations. */
	public function test_normalizes_only_bounded_css_variables(): void {
		$style = EventFilterStyle::from_attributes(
			array(
				'filterPanelBackground' => '#AABBCC',
				'filterFieldText'       => '#112233',
				'filterActionText'      => 'red;display:none',
				'filterGap'             => 24,
				'filterOptionGap'       => 12,
				'filterPanelRadius'     => 999,
				'filterCheckboxSize'    => '16',
			)
		)->inline_style();

		self::assertSame( '--wpse-filter-panel-background:#aabbcc;--wpse-control-text:#112233;--wpse-filter-gap:24px;--wpse-filter-option-gap:12px', $style );
	}

	/** Missing editor values preserve theme-inheriting component defaults. */
	public function test_empty_attributes_add_no_inline_style(): void {
		self::assertSame( '', EventFilterStyle::from_attributes( array() )->inline_style() );
	}
}
