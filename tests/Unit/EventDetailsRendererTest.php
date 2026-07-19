<?php
/**
 * Tests for the backward-compatible composite event-details renderer.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventDetailsRenderer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Term;

#[CoversClass( EventDetailsRenderer::class )]
/**
 * Protects the complete native/shortcode/Elementor details contract.
 */
final class EventDetailsRendererTest extends TestCase {
	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * The refactored composite retains its existing wrappers and field order.
	 */
	public function test_composite_keeps_existing_semantic_markup_and_order(): void {
		$this->add_complete_event( 81 );
		$output  = ( new EventDetailsRenderer() )->render( 81 );
		$classes = array(
			'wpse-single-event-title',
			'wpse-single-event-image',
			'wpse-event-summary',
			'wpse-event-date',
			'wpse-event-status-cancelled',
			'wpse-event-venue',
			'wpse-event-address',
			'wpse-event-location-link',
			'wpse-single-event-content',
			'wpse-event-action',
			'wpse-event-categories',
			'wpse-event-tags',
		);
		$last    = -1;

		foreach ( $classes as $class ) {
			$position = strpos( $output, $class );
			self::assertNotFalse( $position, $class . ' is missing.' );
			self::assertGreaterThan( $last, $position, $class . ' is out of order.' );
			$last = $position;
		}

		self::assertStringContainsString( '<article class="wpse-single-event"', $output );
		self::assertStringContainsString( '<header class="wpse-single-event-header">', $output );
		self::assertStringContainsString( '<div class="wpse-event-location">', $output );
	}

	/**
	 * Explicit rendering is public-only while current protected output is a form.
	 */
	public function test_public_and_password_boundaries_are_centralized(): void {
		$this->add_complete_event( 82, 'draft' );
		self::assertSame( '', ( new EventDetailsRenderer() )->render_public( 82 ) );

		WordPressState::allow_current_user( true );
		self::assertStringContainsString( 'wpse-single-event', ( new EventDetailsRenderer() )->render( 82 ) );

		$this->add_complete_event( 83, 'publish', 'secret' );
		$renderer = new EventDetailsRenderer();
		self::assertSame( '', $renderer->render_public( 83 ) );
		self::assertStringContainsString( 'post-password-form', $renderer->render( 83 ) );
		self::assertStringNotContainsString( 'Complete event', $renderer->render( 83 ) );
	}

	/**
	 * Separate renderer instances cannot recurse through event content.
	 */
	public function test_composite_recursion_guard_is_request_wide(): void {
		$this->add_complete_event( 84 );
		$outer = new EventDetailsRenderer();
		$inner = new EventDetailsRenderer();
		WordPressState::set_filter(
			'the_content',
			static fn ( string $content ): string => $content . $inner->render( 84 )
		);

		$output = $outer->render( 84 );

		self::assertSame( 1, substr_count( $output, 'Complete event' ) );
	}

	/**
	 * Add one complete deterministic event.
	 *
	 * @param int    $id       Event post ID.
	 * @param string $status   Publication status.
	 * @param string $password Optional post password.
	 */
	private function add_complete_event( int $id, string $status = 'publish', string $password = '' ): void {
		$event = new WP_Post(
			array(
				'ID'            => $id,
				'post_type'     => EventPostType::POST_TYPE,
				'post_status'   => $status,
				'post_password' => $password,
				'post_title'    => 'Complete event',
				'post_content'  => '<p>Event content</p>',
			)
		);
		WordPressState::add_post( $event, 'https://example.com/events/complete/', 'https://example.com/image.jpg', 'Event image' );
		$timezone = new DateTimeZone( 'Europe/Brussels' );
		WordPressState::update_post_meta( $id, EventMeta::START_UTC, ( new DateTimeImmutable( '2026-07-20 12:00:00', $timezone ) )->getTimestamp() );
		WordPressState::update_post_meta( $id, EventMeta::END_UTC, ( new DateTimeImmutable( '2026-07-20 14:00:00', $timezone ) )->getTimestamp() );
		WordPressState::update_post_meta( $id, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( $id, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( $id, EventMeta::STATUS, EventStatus::CANCELLED->value );
		WordPressState::update_post_meta( $id, EventMeta::VENUE, 'Venue' );
		WordPressState::update_post_meta( $id, EventMeta::ADDRESS, 'Address' );
		WordPressState::update_post_meta( $id, EventMeta::LOCATION_URL, 'https://example.com/location/' );
		WordPressState::update_post_meta( $id, EventMeta::EVENT_URL, 'https://example.com/info/' );
		WordPressState::set_post_terms( $id, EventTaxonomies::CATEGORY, array( 28 ) );
		WordPressState::set_post_terms( $id, EventTaxonomies::TAG, array( 29 ) );
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 28,
					'name'    => 'Category',
					'slug'    => 'category',
				)
			),
			'https://example.com/event-category/category/'
		);
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 29,
					'name'    => 'Tag',
					'slug'    => 'tag',
				)
			),
			'https://example.com/event-tag/tag/'
		);
	}
}
