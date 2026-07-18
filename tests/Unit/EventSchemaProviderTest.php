<?php
/**
 * Tests for the WordPress event schema adapter.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use DateTimeImmutable;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Seo\EventSchemaProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( EventSchemaProvider::class )]
/**
 * Verifies that only correct public WordPress data reaches schema output.
 */
final class EventSchemaProviderTest extends TestCase {
	/**
	 * Reset the WordPress state used by global function doubles.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * A complete published event is mapped through the shared date formatter.
	 */
	public function test_provides_schema_for_a_public_event(): void {
		$event = $this->event();
		WordPressState::add_post(
			$event,
			'https://example.com/events/summer-concert/',
			'https://example.com/concert.jpg'
		);
		$this->add_valid_meta( $event->ID );

		$schema = ( new EventSchemaProvider() )->provide( $event->ID );

		self::assertIsArray( $schema );
		self::assertSame( 'Summer concert', $schema['name'] ?? null );
		self::assertSame( '2026-07-16T19:30:00+02:00', $schema['startDate'] ?? null );
		self::assertSame( '2026-07-16T21:30:00+02:00', $schema['endDate'] ?? null );
		self::assertSame( 'A visible description.', $schema['description'] ?? null );
		self::assertSame( array( 'https://example.com/concert.jpg' ), $schema['image'] ?? null );
	}

	/**
	 * Non-public posts cannot leak content or event metadata.
	 */
	public function test_rejects_drafts_and_password_protected_events(): void {
		$draft = $this->event( post_status: 'draft' );
		WordPressState::add_post( $draft, 'https://example.com/?p=31' );

		$protected = $this->event( id: 32, password: 'secret' );
		WordPressState::add_post( $protected, 'https://example.com/?p=32' );

		$provider = new EventSchemaProvider();

		self::assertNull( $provider->provide( $draft->ID ) );
		self::assertNull( $provider->provide( $protected->ID ) );
	}

	/**
	 * Invalid dates or an invalid event status suppress the complete graph.
	 */
	public function test_rejects_incomplete_or_corrupt_event_metadata(): void {
		$event = $this->event();
		WordPressState::add_post( $event, 'https://example.com/events/event/' );
		WordPressState::update_post_meta( $event->ID, EventMeta::STATUS, 'unexpected' );

		self::assertNull( ( new EventSchemaProvider() )->provide( $event->ID ) );
	}

	/**
	 * Build one event post.
	 *
	 * @param int    $id          Post ID.
	 * @param string $post_status WordPress publication status.
	 * @param string $password    Optional post password.
	 */
	private function event( int $id = 31, string $post_status = 'publish', string $password = '' ): WP_Post {
		return new WP_Post(
			array(
				'ID'            => $id,
				'post_type'     => EventPostType::POST_TYPE,
				'post_status'   => $post_status,
				'post_password' => $password,
				'post_title'    => '<b>Summer concert</b>',
				'post_excerpt'  => 'A visible description.',
				'post_content'  => 'Longer event content.',
			)
		);
	}

	/**
	 * Store one valid timed event range and public metadata.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function add_valid_meta( int $event_id ): void {
		WordPressState::update_post_meta(
			$event_id,
			EventMeta::START_UTC,
			( new DateTimeImmutable( '2026-07-16T19:30:00+02:00' ) )->getTimestamp()
		);
		WordPressState::update_post_meta(
			$event_id,
			EventMeta::END_UTC,
			( new DateTimeImmutable( '2026-07-16T21:30:00+02:00' ) )->getTimestamp()
		);
		WordPressState::update_post_meta( $event_id, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( $event_id, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( $event_id, EventMeta::STATUS, 'scheduled' );
		WordPressState::update_post_meta( $event_id, EventMeta::VENUE, 'Town Hall' );
		WordPressState::update_post_meta( $event_id, EventMeta::ADDRESS, 'Main Street 1' );
	}
}
