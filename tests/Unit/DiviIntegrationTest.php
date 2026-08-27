<?php
/**
 * Tests for the optional Divi 5 host and post-type integration.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Divi\DiviIntegration;
use MiMe\WPSimpleEvents\Divi\DiviEditorDataProvider;
use MiMe\WPSimpleEvents\Divi\DiviCompositeModuleRenderer;
use MiMe\WPSimpleEvents\Divi\DiviPreviewController;
use MiMe\WPSimpleEvents\Divi\DiviHost;
use MiMe\WPSimpleEvents\Divi\DiviModuleRegistrar;
use MiMe\WPSimpleEvents\Divi\DiviPostTypeIntegration;
use MiMe\WPSimpleEvents\Divi\EventFieldModuleRenderer;
use MiMe\WPSimpleEvents\Divi\EventTitleModuleRenderer;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Query\PublicEventOptions;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( DiviIntegration::class )]
#[CoversClass( DiviPostTypeIntegration::class )]
/**
 * Verifies that Divi remains optional and uses its official CPT filter.
 */
final class DiviIntegrationTest extends TestCase {
	/** Reset recorded hooks before every test. */
	protected function setUp(): void {
		HookRecorder::reset();
	}

	/** The safe dormant post-type filter does not require Divi to be loaded. */
	public function test_registers_only_the_supported_post_type_filter(): void {
		$contexts    = new EventContextResolver();
		$current     = new CurrentEventPresentationResolver( $contexts );
		$fields      = new EventFieldRenderer();
		$shortcode   = new class() implements ShortcodeRenderer {
			/**
			 * Return no output in the dormant host test.
			 *
			 * @param array<string, mixed>|string $attributes Raw attributes.
			 */
			public function render( array|string $attributes = array() ): string {
				unset( $attributes );

				return '';
			}
		};
		$composites  = new DiviCompositeModuleRenderer( $shortcode, $shortcode, $shortcode );
		$integration = new DiviIntegration(
			new DiviPostTypeIntegration(),
			new DiviModuleRegistrar(
				new class() implements DiviHost {
					/** Report that Divi is absent in this optional-host test. */
					public function is_loaded(): bool {
						return false;
					}

					/** Return no product version when Divi is absent. */
					public function version(): ?string {
						return null;
					}
				},
				new EventTitleModuleRenderer( $contexts, $current, $fields ),
				new EventFieldModuleRenderer( $contexts, $current, $fields ),
				$composites,
				new DiviEditorDataProvider( new PublicEventOptions(), $contexts, $current )
			),
			new DiviPreviewController( $composites )
		);

		$integration->register();

		self::assertIsCallable( HookRecorder::action( 'et_builder_third_party_post_types' ) );
		self::assertIsCallable( HookRecorder::action( 'divi_module_library_modules_dependency_tree' ) );
		self::assertIsCallable( HookRecorder::action( 'divi_visual_builder_assets_before_enqueue_styles' ) );
		self::assertIsCallable( HookRecorder::action( 'divi_visual_builder_assets_before_enqueue_scripts' ) );
		self::assertIsCallable( HookRecorder::action( 'rest_api_init' ) );
	}

	/** The adapter preserves unrelated types and adds Events exactly once. */
	public function test_post_type_adapter_is_strict_and_idempotent(): void {
		$adapter = new DiviPostTypeIntegration();

		self::assertSame(
			array( 'product', EventPostType::POST_TYPE ),
			$adapter->add_event_post_type( array( 'product', EventPostType::POST_TYPE ) )
		);
		self::assertSame(
			array( EventPostType::POST_TYPE ),
			$adapter->add_event_post_type( 'invalid' )
		);
	}
}
