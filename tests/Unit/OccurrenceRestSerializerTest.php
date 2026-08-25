<?php
/**
 * Tests for bounded public occurrence REST serialization.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\EventTermPresentation;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Rest\OccurrenceRestSerializer;
use MiMe\WPSimpleEvents\Tests\Support\OccurrencePresentationFixture;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( OccurrenceRestSerializer::class )]
/** Proves the leaf response is effective, bounded and free of internal state. */
final class OccurrenceRestSerializerTest extends TestCase {
	private const KEY = 'dddddddddddddddddddddddddddddddd';

	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::set_option( 'date_format', 'Y-m-d' );
		WordPressState::set_option( 'time_format', 'H:i' );
	}

	/** Effective occurrence fields serialize without projection or metadata internals. */
	public function test_serializes_one_exact_public_occurrence(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 77,
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
				)
			),
			'',
			'https://example.com/poster.jpg'
		);
		$context = $this->context( 77 );
		$data    = ( new OccurrenceRestSerializer() )->serialize(
			$context,
			'https://example.com/events/series/occurrence/' . self::KEY . '/'
		);

		self::assertNotNull( $data );
		self::assertSame( 1, $data['schema_version'] );
		self::assertSame( 42, $data['event_id'] );
		self::assertSame( self::KEY, $data['occurrence_key'] );
		self::assertSame( 'Occurrence block title', $data['title'] );
		self::assertSame( 'Occurrence block note', $data['note'] );
		self::assertSame( '2027-01-05T19:00:00', $data['date']['start_local'] );
		self::assertSame( 'Europe/Brussels', $data['date']['timezone'] );
		self::assertSame( EventStatus::POSTPONED->value, $data['status'] );
		self::assertSame(
			array(
				'id'  => 77,
				'url' => 'https://example.com/poster.jpg',
			),
			$data['featured_image']
		);
		self::assertSame( 'Occurrence block venue', $data['location']['venue'] );
		self::assertSame( 'Occurrence block action', $data['external_action']['label'] );
		self::assertSame( 'Concerts', $data['categories'][0]['name'] );
		self::assertSame( 'Indoor', $data['tags'][0]['name'] );

		$encoded = (string) wp_json_encode( $data );
		self::assertStringNotContainsString( 'recurrence_id', $encoded );
		self::assertStringNotContainsString( 'generation', $encoded );
		self::assertStringNotContainsString( 'segment_id', $encoded );
		self::assertStringNotContainsString( '_wpse_', $encoded );
		self::assertStringNotContainsString( 'Series body', $encoded );
	}

	/** Missing images and external URLs become null instead of leaking orphan values. */
	public function test_unavailable_optional_resources_are_null(): void {
		$context = $this->context( 99, '' );
		$data    = ( new OccurrenceRestSerializer() )->serialize(
			$context,
			'https://example.com/events/series/occurrence/' . self::KEY . '/'
		);

		self::assertNotNull( $data );
		self::assertNull( $data['featured_image'] );
		self::assertNull( $data['external_action'] );
	}

	/** Invalid occurrence canonicals fail before any REST representation exists. */
	public function test_invalid_canonical_fails_closed(): void {
		self::assertNull(
			( new OccurrenceRestSerializer() )->serialize( $this->context(), 'javascript:alert(1)' )
		);
	}

	/**
	 * Build one effective context with public series-owned terms.
	 *
	 * @param int    $image_id Effective attachment ID.
	 * @param string $event_url Effective external action URL.
	 */
	private function context( int $image_id = 0, string $event_url = 'https://example.com/action' ): OccurrencePresentationContext {
		$series  = new EventPresentation(
			new WP_Post(
				array(
					'ID'           => 42,
					'post_type'    => EventPostType::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => 'Series title',
					'post_content' => 'Series body',
				)
			),
			'Series title',
			'https://example.com/events/series/',
			$image_id > 0,
			null,
			EventStatus::SCHEDULED,
			'Series venue',
			'Series address',
			'https://example.com/location',
			$event_url,
			'' === $event_url ? 'Orphaned label' : 'Series action',
			array( new EventTermPresentation( 'Concerts', 'https://example.com/event-category/concerts/' ) ),
			array( new EventTermPresentation( 'Indoor', 'https://example.com/event-tag/indoor/' ) ),
			$image_id
		);
		$fixture = OccurrencePresentationFixture::create( $series, self::KEY );

		return new OccurrencePresentationContext(
			$fixture->series,
			$fixture->occurrence,
			$fixture->title,
			$fixture->note,
			$image_id,
			$fixture->venue,
			$fixture->address,
			$fixture->location_url,
			$event_url,
			'' === $event_url ? 'Orphaned label' : $fixture->event_url_label
		);
	}
}
