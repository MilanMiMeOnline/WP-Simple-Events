<?php
/**
 * Tests for progressively enhanced calendar filter controls.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Shortcode\CalendarControls;
use MiMe\WPSimpleEvents\Shortcode\CalendarShortcodeAttributes;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Term;

/**
 * Verifies controls appear only when visitors have usable choices.
 */
#[CoversClass( CalendarControls::class )]
final class CalendarControlsTest extends TestCase {
	/** Prepare one deterministic page URL for GET form assertions. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post( new WP_Post( array( 'ID' => 91 ) ), 'https://example.test/calendar/' );
		WordPressState::set_singular_event( false, 91 );
	}

	/** No empty filter shell or action is shown without usable terms. */
	public function test_no_terms_omits_the_complete_filter_form(): void {
		self::assertSame( '', $this->render() );
	}

	/** A non-empty category taxonomy renders only its visitor selector. */
	public function test_category_terms_render_a_category_filter(): void {
		WordPressState::set_taxonomy_terms(
			EventTaxonomies::CATEGORY,
			array( $this->term( 3, 'Workshops', 'workshops' ) )
		);

		$output = $this->render();

		self::assertStringContainsString( 'data-wpse-calendar-filters', $output );
		self::assertStringContainsString( 'data-wpse-calendar-filter="category"', $output );
		self::assertStringContainsString( 'value="workshops"', $output );
		self::assertStringNotContainsString( 'data-wpse-calendar-filter="tag"', $output );
		self::assertStringContainsString( 'Apply filters', $output );
	}

	/** A non-empty tag taxonomy renders only its visitor selector. */
	public function test_tag_terms_render_a_tag_filter(): void {
		WordPressState::set_taxonomy_terms(
			EventTaxonomies::TAG,
			array( $this->term( 7, 'Family', 'family' ) )
		);

		$output = $this->render();

		self::assertStringNotContainsString( 'data-wpse-calendar-filter="category"', $output );
		self::assertStringContainsString( 'data-wpse-calendar-filter="tag"', $output );
		self::assertStringContainsString( 'value="family"', $output );
	}

	/** Both taxonomies preserve selections and other calendar instance state. */
	public function test_both_term_types_render_with_get_state_and_reset(): void {
		WordPressState::set_taxonomy_terms(
			EventTaxonomies::CATEGORY,
			array( $this->term( 3, 'Workshops', 'workshops' ) )
		);
		WordPressState::set_taxonomy_terms(
			EventTaxonomies::TAG,
			array( $this->term( 7, 'Family', 'family' ) )
		);
		$attributes = CalendarShortcodeAttributes::from_shortcode(
			array(
				'category' => 'workshops',
				'tag'      => 'family',
			)
		);
		$output     = $this->render(
			$attributes,
			array( 'wpse_calendar_2_tag' => array( 'online' ) )
		);

		self::assertSame( 2, substr_count( $output, 'selected="selected"' ) );
		self::assertStringContainsString( 'name="wpse_calendar_2_tag[]" value="online"', $output );
		self::assertStringContainsString( 'Reset filters', $output );
		self::assertStringContainsString( 'method="get"', $output );
		self::assertStringContainsString( 'aria-controls="wpse-calendar-1-canvas"', $output );
	}

	/**
	 * Render controls with optional normalized state.
	 *
	 * @param CalendarShortcodeAttributes|null $attributes Current filter state.
	 * @param array<string, mixed>             $request    Public request state.
	 */
	private function render( ?CalendarShortcodeAttributes $attributes = null, array $request = array() ): string {
		return ( new CalendarControls() )->render(
			$attributes ?? CalendarShortcodeAttributes::from_shortcode( array() ),
			'wpse_calendar_1',
			'wpse-calendar-1-canvas',
			$request
		);
	}

	/**
	 * Create one public term double.
	 *
	 * @param int    $id   Term ID.
	 * @param string $name Public term name.
	 * @param string $slug Public term slug.
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
