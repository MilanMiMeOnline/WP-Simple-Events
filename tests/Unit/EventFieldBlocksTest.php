<?php
/**
 * Tests for the atomic Gutenberg event-field palette.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use MiMe\WPSimpleEvents\Blocks\EventFieldBlockDefinitions;
use MiMe\WPSimpleEvents\Blocks\EventFieldBlockPattern;
use MiMe\WPSimpleEvents\Blocks\EventFieldBlockRenderer;
use MiMe\WPSimpleEvents\Blocks\EventFieldBlockSettings;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WP_Block;
use WP_Post;
use WP_Term;

#[CoversClass( EventFieldBlockDefinitions::class )]
#[CoversClass( EventFieldBlockPattern::class )]
#[CoversClass( EventFieldBlockRenderer::class )]
#[CoversClass( EventFieldBlockSettings::class )]
/** Protects block metadata, sources, field parity and public empty states. */
final class EventFieldBlocksTest extends TestCase {
	/** Reset and create one complete public event. */
	protected function setUp(): void {
		WordPressState::reset();
		$this->add_complete_event( 191 );
	}

	/**
	 * Every dedicated block consumes the matching named field renderer.
	 *
	 * @param string $block_name Registered block name.
	 * @param string $expected   Expected semantic output marker.
	 */
	#[DataProvider( 'field_blocks' )]
	public function test_every_atomic_block_renders_its_public_field( string $block_name, string $expected ): void {
		$output = $this->renderer()->render(
			array( 'eventId' => 191 ),
			'',
			new WP_Block( array( 'blockName' => $block_name ) )
		);

		self::assertStringContainsString( $expected, $output );
		self::assertStringContainsString( 'wpse-event-field-block', $output );
	}

	/**
	 * Supply every block and its semantic marker.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function field_blocks(): array {
		return array(
			'title'           => array( 'wpse/event-title', 'wpse-single-event-title' ),
			'featured image'  => array( 'wpse/event-featured-image', 'wpse-single-event-image' ),
			'date and time'   => array( 'wpse/event-date-time', 'wpse-event-date' ),
			'status'          => array( 'wpse/event-status', 'wpse-event-status-cancelled' ),
			'venue'           => array( 'wpse/event-venue', 'wpse-event-venue' ),
			'address'         => array( 'wpse/event-address', 'wpse-event-address' ),
			'location link'   => array( 'wpse/event-location-link', 'wpse-event-location-link' ),
			'content'         => array( 'wpse/event-content', 'wpse-single-event-content' ),
			'excerpt'         => array( 'wpse/event-excerpt', 'wpse-event-excerpt' ),
			'external action' => array( 'wpse/event-external-action', 'wpse-event-action' ),
			'categories'      => array( 'wpse/event-categories', 'wpse-event-categories' ),
			'tags'            => array( 'wpse/event-tags', 'wpse-event-tags' ),
		);
	}

	/** Current block context and explicit public selection keep field semantics identical. */
	public function test_explicit_and_context_sources_render_identically(): void {
		$renderer = $this->renderer();
		$block    = new WP_Block(
			array( 'blockName' => 'wpse/event-venue' ),
			array(
				'postId'   => 191,
				'postType' => EventPostType::POST_TYPE,
			)
		);
		$settings = array(
			'showLabel' => true,
			'label'     => 'Place <b>now</b>:',
		);

		self::assertSame(
			$renderer->render(
				array(
					...$settings,
					'eventId' => 191,
				),
				'',
				$block
			),
			$renderer->render( $settings, '', $block )
		);
		self::assertStringContainsString( 'Place now:', $renderer->render( $settings, '', $block ) );
	}

	/** Invalid explicit sources and non-event context fail closed without fallback. */
	public function test_invalid_sources_do_not_fall_back_or_leak(): void {
		foreach (
			array(
				192 => array(
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => 'Draft',
				),
				193 => array(
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => 'secret',
					'post_title'    => 'Protected',
				),
				194 => array(
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'Blog post',
				),
			) as $event_id => $data
		) {
			WordPressState::add_post(
				new WP_Post(
					array(
						'ID' => $event_id,
						...$data,
					)
				)
			);
		}

		$renderer = $this->renderer();
		$current  = new WP_Block(
			array( 'blockName' => 'wpse/event-title' ),
			array(
				'postId'   => 191,
				'postType' => EventPostType::POST_TYPE,
			)
		);

		foreach ( array( 'bad', -1, 192, 193, 194 ) as $event_id ) {
			self::assertSame( '', $renderer->render( array( 'eventId' => $event_id ), '', $current ) );
		}

		self::assertSame(
			'',
			$renderer->render(
				array(),
				'',
				new WP_Block(
					array( 'blockName' => 'wpse/event-title' ),
					array(
						'postId'   => 194,
						'postType' => 'post',
					)
				)
			)
		);
	}

	/** Malformed field controls are allowlisted and plain-text sanitized. */
	public function test_field_controls_are_strictly_normalized(): void {
		$renderer = $this->renderer();
		$image    = $renderer->render(
			array(
				'eventId'   => 191,
				'imageSize' => '../../raw',
				'altMode'   => 'decorative',
				'link'      => true,
			),
			'',
			new WP_Block( array( 'blockName' => 'wpse/event-featured-image' ) )
		);
		$action   = $renderer->render(
			array(
				'eventId'  => 191,
				'linkText' => 'Parking <script>x</script>plan',
			),
			'',
			new WP_Block( array( 'blockName' => 'wpse/event-external-action' ) )
		);

		self::assertStringContainsString( 'attachment-large size-large', $image );
		self::assertStringContainsString( 'alt=""', $image );
		self::assertStringContainsString( 'wpse-event-image-link', $image );
		self::assertStringContainsString( '>Parking plan</a>', $action );
		self::assertFalse( EventFieldBlockSettings::boolean( array( 'link' => 'yes' ), 'link', false ) );
	}

	/** Empty fields and unknown blocks produce no public wrapper. */
	public function test_empty_and_unknown_blocks_emit_nothing(): void {
		WordPressState::update_post_meta( 191, EventMeta::STATUS, EventStatus::SCHEDULED->value );
		$renderer = $this->renderer();

		self::assertSame( '', $renderer->render( array( 'eventId' => 191 ), '', new WP_Block( array( 'blockName' => 'wpse/event-status' ) ) ) );
		self::assertSame( '', $renderer->render( array( 'eventId' => 191 ), '', new WP_Block( array( 'blockName' => 'wpse/raw-meta' ) ) ) );
	}

	/** Metadata definitions and the opt-in pattern cover the complete stable palette. */
	public function test_metadata_and_pattern_cover_the_complete_palette(): void {
		self::assertSame( array_keys( self::field_blocks() ), EventFieldBlockDefinitions::labels() );
		self::assertCount( 12, EventFieldBlockDefinitions::slugs() );

		foreach ( EventFieldBlockDefinitions::slugs() as $slug ) {
			$path     = dirname( __DIR__, 2 ) . '/blocks/' . $slug . '/block.json';
			$metadata = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads one trusted local test fixture.

			self::assertSame( 'wpse/' . $slug, $metadata['name'] ?? null );
			self::assertSame( 3, $metadata['apiVersion'] ?? null );
			self::assertSame( array( 'postId', 'postType' ), $metadata['usesContext'] ?? null );
			self::assertSame( 'wpse-event-fields-editor', $metadata['editorScript'] ?? null );
			self::assertFalse( $metadata['supports']['html'] ?? true );
		}

		$pattern = ( new EventFieldBlockPattern() )->content();
		foreach ( EventFieldBlockDefinitions::slugs() as $slug ) {
			self::assertStringContainsString( '<!-- wp:wpse/' . $slug, $pattern );
		}
	}

	/** Create a block renderer with request-shared presentation services. */
	private function renderer(): EventFieldBlockRenderer {
		return new EventFieldBlockRenderer( new EventContextResolver(), new EventFieldRenderer() );
	}

	/**
	 * Create one complete event fixture for the whole atomic palette.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function add_complete_event( int $event_id ): void {
		$event = new WP_Post(
			array(
				'ID'           => $event_id,
				'post_type'    => EventPostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Block event',
				'post_excerpt' => 'Short summary',
				'post_content' => '<p>Complete description</p>',
			)
		);
		WordPressState::add_post( $event, 'https://example.com/events/block/', 'https://example.com/poster.jpg', 'Event poster' );
		$timezone = new DateTimeZone( 'Europe/Brussels' );
		WordPressState::update_post_meta( $event_id, EventMeta::START_UTC, ( new DateTimeImmutable( '2026-07-20 12:00:00', $timezone ) )->getTimestamp() );
		WordPressState::update_post_meta( $event_id, EventMeta::END_UTC, ( new DateTimeImmutable( '2026-07-20 14:00:00', $timezone ) )->getTimestamp() );
		WordPressState::update_post_meta( $event_id, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( $event_id, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( $event_id, EventMeta::STATUS, EventStatus::CANCELLED->value );
		WordPressState::update_post_meta( $event_id, EventMeta::VENUE, 'Main Hall' );
		WordPressState::update_post_meta( $event_id, EventMeta::ADDRESS, "High Street 1\nBrussels" );
		WordPressState::update_post_meta( $event_id, EventMeta::LOCATION_URL, 'https://example.com/route/' );
		WordPressState::update_post_meta( $event_id, EventMeta::EVENT_URL, 'https://example.com/register/' );
		WordPressState::update_post_meta( $event_id, EventMeta::EVENT_URL_LABEL, 'Register now' );
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 41,
					'name'    => 'Music',
					'slug'    => 'music',
				)
			),
			'https://example.com/event-category/music/'
		);
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 42,
					'name'    => 'Live',
					'slug'    => 'live',
				)
			),
			'https://example.com/event-tag/live/'
		);
		WordPressState::set_post_terms( $event_id, EventTaxonomies::CATEGORY, array( 41 ) );
		WordPressState::set_post_terms( $event_id, EventTaxonomies::TAG, array( 42 ) );
	}
}
