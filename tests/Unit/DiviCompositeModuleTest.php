<?php
/**
 * Tests for composite Divi setting and rendering adapters.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Divi\DiviCompositeModuleRenderer;
use MiMe\WPSimpleEvents\Divi\DiviCompositeSettings;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( DiviCompositeSettings::class )]
#[CoversClass( DiviCompositeModuleRenderer::class )]
/** Verifies strict Divi normalization and shared-renderer delegation. */
final class DiviCompositeModuleTest extends TestCase {
	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** List and calendar controls are allowlisted, bounded and fail safe. */
	public function test_normalizes_query_module_settings(): void {
		$attrs = $this->attrs(
			array(
				'view'             => 'invalid',
				'period'           => 'past',
				'limit'            => '999',
				'columns'          => '4',
				'categories'       => array( 'Music Events', '***', 'music-events' ),
				'tags'             => array(
					'inside'  => 'on',
					'ignored' => 'off',
				),
				'filters'          => 'on',
				'pagination'       => 'invalid',
				'excerptLength'    => '65',
				'initialView'      => 'list',
				'mobileView'       => 'invalid',
				'initialDate'      => '2027-02-29',
				'showNavigation'   => 'off',
				'showToday'        => 'on',
				'showViewSwitcher' => 'off',
			)
		);

		$list = DiviCompositeSettings::event_list( $attrs );
		self::assertSame( 'grid', $list['view'] );
		self::assertSame( 'past', $list['period'] );
		self::assertSame( 12, $list['limit'] );
		self::assertSame( 4, $list['columns'] );
		self::assertSame( array( 'music-events' ), $list['category'] );
		self::assertSame( array( 'inside' ), $list['tag'] );
		self::assertTrue( $list['filters'] );
		self::assertTrue( $list['pagination'] );
		self::assertSame( 65, $list['excerpt_length'] );

		$calendar = DiviCompositeSettings::calendar( $attrs );
		self::assertSame( 'list', $calendar['initial_view'] );
		self::assertSame( 'list', $calendar['mobile_view'] );
		self::assertSame( '', $calendar['initial_date'] );
		self::assertFalse( $calendar['show_navigation'] );
		self::assertTrue( $calendar['show_today'] );
		self::assertFalse( $calendar['show_view_switcher'] );
		self::assertSame( 'auto', $calendar['legend'] );
	}

	/** Details preview adds only a valid explicit or exact event context ID. */
	public function test_details_delegate_uses_exact_public_context(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 42,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
				)
			)
		);
		$renderer = $this->renderer();
		$output   = $renderer->render( 'details', $this->attrs( array( 'showTitle' => 'off' ) ), 42 );
		$data     = json_decode( $output, true );

		self::assertIsArray( $data );
		self::assertSame( 42, $data['id'] );
		self::assertFalse( $data['show_title'] );
		self::assertSame( '', $renderer->render( 'unknown', array(), 42 ) );
	}

	/** Optional Divi design values become only strict component-scoped variables. */
	public function test_filter_design_values_are_bounded_before_rendering(): void {
		$renderer = $this->renderer();
		$output   = $renderer->render(
			'list',
			$this->attrs(
				array(
					'filterPanelBackground' => '#AABBCC',
					'filterFieldText'       => '#112233',
					'filterActionText'      => 'red;display:none',
					'filterGap'             => '18',
					'filterOptionGap'       => '7',
					'filterPanelRadius'     => '999',
				)
			)
		);

		self::assertStringContainsString( 'class="wpse-divi-filter-style"', $output );
		self::assertStringContainsString( '--wpse-filter-panel-background:#aabbcc', $output );
		self::assertStringContainsString( '--wpse-control-text:#112233', $output );
		self::assertStringContainsString( '--wpse-filter-gap:18px', $output );
		self::assertStringContainsString( '--wpse-filter-option-gap:7px', $output );
		self::assertStringNotContainsString( 'display:none', $output );
		self::assertStringNotContainsString( '999px', $output );
	}

	/** Add to Calendar keeps provider defaults, exact preview context and bounded design values. */
	public function test_calendar_action_uses_shared_contract_and_bounded_style(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 42,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
				)
			)
		);
		$output = $this->renderer()->render(
			'calendar_action',
			$this->attrs(
				array(
					'providerGoogle'      => 'on',
					'providerOutlook'     => 'invalid',
					'layout'              => 'list',
					'label'               => 'Save this date',
					'actionBackground'    => '#AABBCC',
					'actionBorder'        => 'red;display:none',
					'actionRadius'        => '18',
					'actionPaddingInline' => '999',
				)
			),
			42
		);

		self::assertStringContainsString( 'class="wpse-divi-calendar-action-style"', $output );
		self::assertStringContainsString( '--wpse-calendar-action-background:#aabbcc', $output );
		self::assertStringContainsString( '--wpse-calendar-action-radius:18px', $output );
		self::assertStringNotContainsString( 'display:none', $output );
		self::assertStringNotContainsString( '999px', $output );
		self::assertStringContainsString( '"providers":["ics","google"]', $output );
		self::assertStringContainsString( '"layout":"list"', $output );
		self::assertStringContainsString( '"label":"Save this date"', $output );
		self::assertStringContainsString( '"id":42', $output );
	}

	/**
	 * Build nested values matching Divi's non-responsive event group.
	 *
	 * @param array<string, mixed> $values Raw setting values.
	 */
	private function attrs( array $values ): array {
		return array(
			'event' => array(
				'innerContent' => array(
					'desktop' => array( 'value' => $values ),
				),
			),
		);
	}

	/** Build deterministic native renderers that expose normalized attributes. */
	private function renderer(): DiviCompositeModuleRenderer {
		$renderer = new class() implements ShortcodeRenderer {
			/**
			 * Return normalized attributes for assertion.
			 *
			 * @param array<string, mixed>|string $attributes Raw attributes.
			 */
			public function render( array|string $attributes = array() ): string {
				return is_array( $attributes ) ? (string) wp_json_encode( $attributes ) : '';
			}
		};

		return new DiviCompositeModuleRenderer( $renderer, $renderer, $renderer, $renderer );
	}
}
