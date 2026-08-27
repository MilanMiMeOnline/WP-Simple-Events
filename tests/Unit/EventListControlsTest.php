<?php
/**
 * Tests for event-list visitor filter controls.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Shortcode\EventListAttributes;
use MiMe\WPSimpleEvents\Shortcode\EventListControls;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Term;

#[CoversClass( EventListControls::class )]
/** Protects per-instance GET state and an explicit reset path. */
final class EventListControlsTest extends TestCase {
	/** Reset the isolated WordPress test state. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post( new WP_Post( array( 'ID' => 91 ) ), 'https://example.test/events/' );
		WordPressState::set_singular_event( false, 91 );
	}

	/** Applying a filter can explicitly clear initial terms and then reset them. */
	public function test_form_uses_a_submission_marker_and_reset_url(): void {
		$configured = EventListAttributes::from_shortcode(
			array(
				'filters'  => true,
				'category' => 'initial',
			)
		);
		$attributes = $configured->with_request(
			array(
				'wpse_1_apply'    => '1',
				'wpse_1_period'   => 'all',
				'wpse_2_category' => array( 'other' ),
			),
			'wpse_1'
		);
		$output     = ( new EventListControls() )->filters(
			$attributes,
			'wpse_1',
			'wpse-events-1-results',
			array(
				'wpse_1_apply'    => '1',
				'wpse_1_period'   => 'all',
				'wpse_2_category' => array( 'other' ),
			),
			$configured
		);

		self::assertSame( array(), $attributes->category_slugs );
		self::assertStringContainsString( 'name="wpse_1_apply" value="1"', $output );
		self::assertStringContainsString( 'Clear all', $output );
		self::assertStringContainsString( 'Restore defaults', $output );
		self::assertStringContainsString( 'wpse_2_category', $output );
		self::assertStringNotContainsString( 'wpse_1_period=all', $output );
	}

	/** Category choices use ordinary checkboxes without a modifier-key interaction. */
	public function test_term_filters_render_as_a_semantic_checkbox_group(): void {
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

		$output = ( new EventListControls() )->filters(
			EventListAttributes::from_shortcode(
				array(
					'filters'  => true,
					'category' => 'workshops',
				)
			),
			'wpse_1',
			'wpse-events-1-results',
			array()
		);

		self::assertStringContainsString( '<fieldset class="wpse-events-filter-group"', $output );
		self::assertStringContainsString( '<legend>Categories</legend>', $output );
		self::assertStringContainsString( 'type="checkbox"', $output );
		self::assertStringContainsString( 'name="wpse_1_category[]"', $output );
		self::assertStringContainsString( 'checked="checked"', $output );
		self::assertStringNotContainsString( 'multiple', $output );
	}
}
