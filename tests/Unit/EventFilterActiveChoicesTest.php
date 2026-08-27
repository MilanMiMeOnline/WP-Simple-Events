<?php
/**
 * Tests for removable active event filters.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Frontend\EventFilterActiveChoices;
use MiMe\WPSimpleEvents\Frontend\EventFilterViewModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Term;

/** Protects chip removal, clear and restore semantics. */
#[CoversClass( EventFilterActiveChoices::class )]
final class EventFilterActiveChoicesTest extends TestCase {
	/** Every selected known term gets one escaped, independently removable chip. */
	public function test_renders_removable_chips_and_distinct_global_actions(): void {
		$output = ( new EventFilterActiveChoices() )->render(
			new EventFilterViewModel(
				'https://example.test/events/',
				array( 'wpse_2_apply' => '1' ),
				array(
					'wpse_1_apply'    => '1',
					'wpse_1_period'   => 'all',
					'wpse_1_category' => array( 'talks' ),
					'wpse_1_tag'      => array( 'family' ),
				),
				'wpse_1_category',
				'wpse_1_tag',
				array( $this->term( 1, '<b>Talks</b>', 'talks' ) ),
				array( $this->term( 2, 'Family', 'family' ) ),
				array( 'talks' ),
				array( 'family' ),
				'https://example.test/events/?wpse_1_apply=1',
				'https://example.test/events/',
				true
			)
		);

		self::assertStringContainsString( 'aria-label="Active filters"', $output );
		self::assertStringContainsString( 'Category: Talks', $output );
		self::assertStringNotContainsString( '<b>', $output );
		self::assertStringContainsString( 'Tag: Family', $output );
		self::assertSame( 2, substr_count( $output, 'data-wpse-filter-remove' ) );
		self::assertStringContainsString( 'Clear categories', $output );
		self::assertStringContainsString( 'Clear tags', $output );
		self::assertStringContainsString( 'Clear all', $output );
		self::assertStringContainsString( 'Restore defaults', $output );
		self::assertStringContainsString( 'wpse_2_apply', $output );
	}

	/** Unknown or no-longer-public selections never become trusted chip content. */
	public function test_omits_unknown_selected_terms(): void {
		$output = ( new EventFilterActiveChoices() )->render(
			new EventFilterViewModel(
				'https://example.test/events/',
				array(),
				array( 'wpse_1_category' => array( 'missing' ) ),
				'wpse_1_category',
				'wpse_1_tag',
				array(),
				array(),
				array( 'missing' ),
				array(),
				'https://example.test/events/',
				'',
				true
			)
		);

		self::assertSame( '', $output );
	}

	/**
	 * Build one deterministic public term.
	 *
	 * @param int    $id   Term ID.
	 * @param string $name Term name.
	 * @param string $slug Term slug.
	 */
	private function term( int $id, string $name, string $slug ): WP_Term {
		return new WP_Term(
			array(
				'term_id' => $id,
				'name'    => $name,
				'slug'    => $slug,
			)
		);
	}
}
