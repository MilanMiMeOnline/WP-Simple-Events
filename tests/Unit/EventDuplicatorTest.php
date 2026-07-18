<?php
/**
 * Tests for WordPress event duplication persistence.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventDuplicator;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Post;

#[CoversClass( EventDuplicator::class )]
/**
 * Verifies complete allowlisted copying and failure atomicity.
 */
final class EventDuplicatorTest extends TestCase {
	/**
	 * Reset deterministic WordPress persistence state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * A successful duplicate copies agreed fields and marks dates for review.
	 */
	public function test_duplicates_allowlisted_event_data_into_a_draft(): void {
		$source = new WP_Post(
			array(
				'ID'           => 88,
				'post_type'    => EventPostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Workshop',
				'post_excerpt' => 'Summary',
				'post_content' => 'Content',
			)
		);
		WordPressState::add_post( $source );
		WordPressState::update_post_meta( 88, EventMeta::START_LOCAL, '2026-10-20T10:00:00' );
		WordPressState::update_post_meta( 88, EventMeta::START_UTC, 1_792_483_200 );
		WordPressState::update_post_meta( 88, EventMeta::VENUE, 'Town Hall' );
		WordPressState::update_post_meta( 88, EventMeta::EVENT_URL, 'https://tickets.example.com/' );
		WordPressState::update_post_meta( 88, '_third_party_secret', 'do-not-copy' );
		WordPressState::set_post_terms( 88, EventTaxonomies::CATEGORY, array( 4, 9 ) );
		WordPressState::set_post_terms( 88, EventTaxonomies::TAG, array( 12 ) );

		$result = ( new EventDuplicator() )->duplicate( 88 );

		self::assertSame( 1001, $result );
		self::assertSame( 'draft', WordPressState::inserted_post_data()['post_status'] ?? null );
		self::assertSame( 'Workshop — Copy', WordPressState::inserted_post_data()['post_title'] ?? null );
		self::assertSame( '2026-10-20T10:00:00', WordPressState::post_meta( 1001, EventMeta::START_LOCAL ) );
		self::assertSame( 'Town Hall', WordPressState::post_meta( 1001, EventMeta::VENUE ) );
		self::assertTrue( WordPressState::post_meta( 1001, EventMeta::DATES_NEED_REVIEW ) );
		self::assertFalse( WordPressState::has_post_meta( 1001, EventMeta::EVENT_URL ) );
		self::assertFalse( WordPressState::has_post_meta( 1001, '_third_party_secret' ) );
		self::assertSame( array( 4, 9 ), WordPressState::post_terms( 1001, EventTaxonomies::CATEGORY ) );
		self::assertSame( array( 12 ), WordPressState::post_terms( 1001, EventTaxonomies::TAG ) );
	}

	/**
	 * A term-copy failure rolls the partially created draft back.
	 */
	public function test_rolls_back_when_a_required_copy_step_fails(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'         => 89,
					'post_type'  => EventPostType::POST_TYPE,
					'post_title' => 'Broken copy',
				)
			)
		);
		WordPressState::fail_term_operations( true );

		$result = ( new EventDuplicator() )->duplicate( 89 );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertContains( 1001, WordPressState::deleted_post_ids() );
	}

	/**
	 * Unrelated posts are rejected before a draft is inserted.
	 */
	public function test_rejects_non_event_source(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 90,
					'post_type' => 'post',
				)
			)
		);

		$result = ( new EventDuplicator() )->duplicate( 90 );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( array(), WordPressState::inserted_post_data() );
	}
}
