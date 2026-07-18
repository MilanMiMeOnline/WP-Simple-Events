<?php
/**
 * Tests for the explicit event duplication policy.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventDuplicatePlan;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( EventDuplicatePlan::class )]
/**
 * Prevents duplication from drifting into an arbitrary post/meta clone.
 */
final class EventDuplicatePlanTest extends TestCase {
	/**
	 * A duplicate is always a password-free draft with copied editorial text.
	 */
	public function test_builds_new_draft_post_data(): void {
		$source = new WP_Post(
			array(
				'ID'            => 77,
				'post_type'     => EventPostType::POST_TYPE,
				'post_status'   => 'publish',
				'post_password' => 'source-secret',
				'post_title'    => 'Workshop',
				'post_excerpt'  => 'Short summary',
				'post_content'  => 'Full content',
			)
		);

		$data = ( new EventDuplicatePlan() )->post_data( $source );

		self::assertSame( EventPostType::POST_TYPE, $data['post_type'] ?? null );
		self::assertSame( 'draft', $data['post_status'] ?? null );
		self::assertSame( 'Workshop — Copy', $data['post_title'] ?? null );
		self::assertSame( 'Short summary', $data['post_excerpt'] ?? null );
		self::assertSame( 'Full content', $data['post_content'] ?? null );
		self::assertSame( '', $data['post_password'] ?? null );
		self::assertArrayNotHasKey( 'ID', $data );
	}

	/**
	 * Only agreed event/location metadata and the featured image are copied.
	 */
	public function test_meta_allowlist_excludes_commercial_and_arbitrary_data(): void {
		$keys = ( new EventDuplicatePlan() )->meta_keys();

		self::assertContains( EventMeta::START_LOCAL, $keys );
		self::assertContains( EventMeta::END_UTC, $keys );
		self::assertContains( EventMeta::LOCATION_URL, $keys );
		self::assertContains( '_thumbnail_id', $keys );
		self::assertNotContains( EventMeta::EVENT_URL, $keys );
		self::assertNotContains( EventMeta::DATES_NEED_REVIEW, $keys );
		self::assertNotContains( '_third_party_secret', $keys );
	}

	/**
	 * Event taxonomies are copied without blog or third-party terms.
	 */
	public function test_taxonomy_allowlist_is_event_specific(): void {
		self::assertSame(
			array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG ),
			( new EventDuplicatePlan() )->taxonomies()
		);
	}
}
