<?php
/**
 * Tests for authenticated Divi composite previews.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Divi\DiviCompositeModuleRenderer;
use MiMe\WPSimpleEvents\Divi\DiviPreviewController;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

#[CoversClass( DiviPreviewController::class )]
/** Verifies capability checks and bounded preview transport. */
final class DiviPreviewControllerTest extends TestCase {
	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** Preview permission requires both an existing post and edit_post capability. */
	public function test_permission_is_exact_and_fails_closed(): void {
		$controller = $this->controller();
		$request    = $this->request( 42, 'list', array() );

		self::assertFalse( $controller->can_preview( $request ) );

		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => 'page',
				)
			)
		);
		self::assertFalse( $controller->can_preview( $request ) );

		WordPressState::allow_current_user( true );
		self::assertTrue( $controller->can_preview( $request ) );
	}

	/** Invalid modules and oversized nested attributes are rejected before rendering. */
	public function test_transport_validation_is_bounded(): void {
		$controller = $this->controller();

		self::assertTrue( $controller->valid_module( 'calendar' ) );
		self::assertTrue( $controller->valid_module( 'calendar_action' ) );
		self::assertFalse( $controller->valid_module( 'unknown' ) );
		self::assertTrue( $controller->valid_attrs( array( 'event' => array() ) ) );
		self::assertFalse( $controller->valid_attrs( 'invalid' ) );
		self::assertFalse( $controller->valid_attrs( array( 'value' => str_repeat( 'a', 21000 ) ) ) );

		$response = $controller->preview( $this->request( 42, 'unknown', array() ) );
		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'wpse_invalid_divi_preview', $response->get_error_code() );
	}

	/** Valid requests return only the shared renderer's escaped HTML envelope. */
	public function test_valid_preview_returns_native_html_envelope(): void {
		$response = $this->controller()->preview(
			$this->request( 42, 'list', array( 'event' => array() ) )
		);

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame(
			array(
				'html'  => '<div class="safe">list</div>',
				'empty' => false,
			),
			$response->get_data()
		);
	}

	/**
	 * Build one strict REST request.
	 *
	 * @param int                  $post_id Editor post ID.
	 * @param string               $module  Allowlisted module key.
	 * @param array<string, mixed> $attrs   Nested Divi attributes.
	 */
	private function request( int $post_id, string $module, array $attrs ): WP_REST_Request {
		$request = new WP_REST_Request();
		$request->set_param( 'postId', $post_id );
		$request->set_param( 'module', $module );
		$request->set_param( 'attrs', $attrs );

		return $request;
	}

	/** Build a controller whose native renderers return deterministic safe markup. */
	private function controller(): DiviPreviewController {
		$renderer = new class() implements ShortcodeRenderer {
			/**
			 * Return deterministic already-escaped native markup.
			 *
			 * @param array<string, mixed>|string $attributes Raw attributes.
			 */
			public function render( array|string $attributes = array() ): string {
				unset( $attributes );

				return '<div class="safe">list</div>';
			}
		};

		return new DiviPreviewController( new DiviCompositeModuleRenderer( $renderer, $renderer, $renderer, $renderer ) );
	}
}
