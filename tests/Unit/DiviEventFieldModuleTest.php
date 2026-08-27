<?php
/**
 * Tests for the native Divi atomic field adapter.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Divi\DiviModuleSettings;
use MiMe\WPSimpleEvents\Divi\EventFieldModuleRenderer;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( DiviModuleSettings::class )]
#[CoversClass( EventFieldModuleRenderer::class )]
/** Protects atomic field source resolution and strict setting normalization. */
final class DiviEventFieldModuleTest extends TestCase {
	/** Add one complete public event before each assertion. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 301,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => 'Public field event',
				)
			),
			'https://example.com/events/public-field-event/'
		);
		WordPressState::update_post_meta( 301, EventMeta::VENUE, 'Town Hall' );
		WordPressState::update_post_meta( 301, EventMeta::ADDRESS, "Main Street 1\nBrussels" );
		WordPressState::update_post_meta( 301, EventMeta::LOCATION_URL, 'https://example.com/location/' );
		WordPressState::update_post_meta( 301, EventMeta::EVENT_URL, 'https://example.com/register/' );
		WordPressState::update_post_meta( 301, EventMeta::EVENT_URL_LABEL, 'Register' );
	}

	/** All known scalar fields use the shared escaped renderer. */
	public function test_renders_known_fields_for_an_explicit_public_event(): void {
		$attrs = $this->attrs(
			array(
				'eventId'   => '301',
				'showLabel' => 'off',
				'linkText'  => 'Open route',
			)
		);

		self::assertSame( '<p class="wpse-event-venue">Town Hall</p>', $this->renderer()->render( 'venue', $attrs ) );
		self::assertStringContainsString( 'Main Street 1', $this->renderer()->render( 'address', $attrs ) );
		self::assertStringContainsString( '>Open route</a>', $this->renderer()->render( 'location_action', $attrs ) );
		self::assertStringContainsString( '>Open route</a>', $this->renderer()->render( 'external_action', $attrs ) );
	}

	/** Unknown fields and non-public or password-protected explicit sources fail closed. */
	public function test_rejects_unknown_fields_and_protected_sources(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 302,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => 'Private event',
				)
			)
		);
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => 303,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => 'secret',
					'post_title'    => 'Password-protected event',
				)
			)
		);
		WordPressState::update_post_meta( 303, EventMeta::VENUE, 'Secret Hall' );

		self::assertSame( '', $this->renderer()->render( 'unknown', $this->attrs( array( 'eventId' => '301' ) ) ) );
		self::assertSame( '', $this->renderer()->render( 'venue', $this->attrs( array( 'eventId' => '302' ) ) ) );
		self::assertSame( '', $this->renderer()->render( 'venue', $this->attrs( array( 'eventId' => '303' ) ) ) );
	}

	/** Malformed and oversized values fall back instead of overflowing. */
	public function test_normalizes_atomic_settings_with_strict_bounds(): void {
		$attrs = $this->attrs(
			array(
				'eventId'   => '99999999999999999999999999999',
				'showLabel' => 'on',
				'imageSize' => 'php://filter',
				'label'     => '<b>Location</b>',
			)
		);

		self::assertSame( 0, DiviModuleSettings::event_id( $attrs ) );
		self::assertTrue( DiviModuleSettings::toggle( $attrs, 'showLabel' ) );
		self::assertSame( 'large', DiviModuleSettings::choice( $attrs, 'imageSize', array( 'large' ), 'large' ) );
		self::assertSame( 'Location', DiviModuleSettings::text( $attrs, 'label' ) );
	}

	/** Build the host-neutral module renderer under test. */
	private function renderer(): EventFieldModuleRenderer {
		$contexts = new EventContextResolver();

		return new EventFieldModuleRenderer(
			$contexts,
			new CurrentEventPresentationResolver( $contexts ),
			new EventFieldRenderer()
		);
	}

	/**
	 * Nest values in Divi's non-responsive field contract.
	 *
	 * @param array<string, mixed> $values Field values.
	 * @return array<string, mixed>
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
}
