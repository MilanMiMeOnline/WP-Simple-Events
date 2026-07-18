<?php
/**
 * Tests for singular event schema output.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use DateTimeImmutable;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Seo\StructuredDataController;
use MiMe\WPSimpleEvents\Seo\StructuredDataSettings;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( StructuredDataController::class )]
#[CoversClass( StructuredDataSettings::class )]
/**
 * Verifies request and setting boundaries around schema output.
 */
final class StructuredDataControllerTest extends TestCase {
	/**
	 * Reset all shared function-double state.
	 */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/**
	 * Registration uses the front-end document head.
	 */
	public function test_registers_head_callback(): void {
		$controller = new StructuredDataController();

		$controller->register();

		self::assertSame( array( $controller, 'render' ), HookRecorder::action( 'wp_head' ) );
	}

	/**
	 * Eligible singular events receive one safe JSON-LD document.
	 */
	public function test_renders_on_an_enabled_public_event_singular(): void {
		$this->configure_public_event();
		WordPressState::set_singular_event( true, 41 );

		ob_start();
		( new StructuredDataController() )->render();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( '<script type="application/ld+json">', $output );
		self::assertStringContainsString( 'Public event', $output );
	}

	/**
	 * Non-singular requests and the global off setting produce no output.
	 */
	public function test_suppresses_non_singular_and_disabled_output(): void {
		$this->configure_public_event();

		ob_start();
		( new StructuredDataController() )->render();
		self::assertSame( '', ob_get_clean() );

		WordPressState::set_singular_event( true, 41 );
		WordPressState::set_option( StructuredDataSettings::OPTION, false );

		ob_start();
		( new StructuredDataController() )->render();
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Configure one complete published event.
	 */
	private function configure_public_event(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => 41,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => '',
					'post_title'    => 'Public event',
				)
			),
			'https://example.com/events/public-event/'
		);
		WordPressState::update_post_meta(
			41,
			EventMeta::START_UTC,
			( new DateTimeImmutable( '2026-10-20T10:00:00+02:00' ) )->getTimestamp()
		);
		WordPressState::update_post_meta(
			41,
			EventMeta::END_UTC,
			( new DateTimeImmutable( '2026-10-20T11:00:00+02:00' ) )->getTimestamp()
		);
		WordPressState::update_post_meta( 41, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( 41, EventMeta::STATUS, 'scheduled' );
	}
}
