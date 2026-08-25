<?php
/**
 * Tests for occurrence field inheritance resolution.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Application\RecurrenceOccurrenceInheritanceResolver;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Proves series values are normalized before entering an editor response.
 */
#[CoversClass( RecurrenceOccurrenceInheritanceResolver::class )]
final class RecurrenceOccurrenceInheritanceResolverTest extends TestCase {
	/** Reset deterministic WordPress storage. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** Stored series fields are normalized and mapped without exposing meta keys. */
	public function test_resolves_normalized_series_fields(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'         => 42,
					'post_type'  => EventPostType::POST_TYPE,
					'post_title' => '<strong>Series title</strong>',
				)
			)
		);
		WordPressState::update_post_meta( 42, '_thumbnail_id', '77' );
		WordPressState::update_post_meta( 42, EventMeta::VENUE, '<em>Main hall</em>' );
		WordPressState::update_post_meta( 42, EventMeta::ADDRESS, "Main Street 1\nBrussels" );
		WordPressState::update_post_meta( 42, EventMeta::LOCATION_URL, 'javascript:alert(1)' );
		WordPressState::update_post_meta( 42, EventMeta::EVENT_URL, 'https://example.com/event' );
		WordPressState::update_post_meta( 42, EventMeta::EVENT_URL_LABEL, '<b>Register</b>' );

		$fields = ( new RecurrenceOccurrenceInheritanceResolver() )->resolve( 42 );

		self::assertSame( 'Series title', $fields->title );
		self::assertSame( '', $fields->note );
		self::assertSame( 77, $fields->featured_image_id );
		self::assertSame( 'Main hall', $fields->venue );
		self::assertSame( "Main Street 1\nBrussels", $fields->address );
		self::assertSame( '', $fields->location_url );
		self::assertSame( 'https://example.com/event', $fields->event_url );
		self::assertSame( 'Register', $fields->event_url_label );
	}

	/** A non-event ID is never accepted as an inheritance source. */
	public function test_rejects_non_event_post(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => 'post',
				)
			)
		);

		$this->expectException( InvalidArgumentException::class );

		( new RecurrenceOccurrenceInheritanceResolver() )->resolve( 42 );
	}
}
