<?php
/**
 * Tests for named public event-field rendering.
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
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Frontend\EventTermPresentation;
use MiMe\WPSimpleEvents\Frontend\EventTimezoneDisplaySettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Term;

#[CoversClass( EventFieldRenderer::class )]
#[CoversClass( EventTermPresentation::class )]
/**
 * Verifies every atomic field's present, absent and security boundaries.
 */
final class EventFieldRendererTest extends TestCase {
	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Every populated field returns scoped, escaped and semantic output.
	 */
	public function test_renders_all_named_fields_from_one_presentation(): void {
		$event = new WP_Post(
			array(
				'ID'           => 71,
				'post_type'    => EventPostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Summer <Event>',
				'post_excerpt' => 'Short <strong>summary</strong>',
				'post_content' => '<p>Full <strong>details</strong></p>',
			)
		);
		WordPressState::add_post(
			$event,
			'https://example.com/events/summer/',
			'https://example.com/image.jpg',
			'Poster <wide>'
		);
		$this->set_valid_meta( 71 );
		WordPressState::set_option( EventTimezoneDisplaySettings::OPTION, true );
		WordPressState::set_post_terms( 71, EventTaxonomies::CATEGORY, array( 8 ) );
		WordPressState::set_post_terms( 71, EventTaxonomies::TAG, array( 9 ) );
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 8,
					'name'    => 'Music & Arts',
					'slug'    => 'music',
				)
			),
			'https://example.com/event-category/music/'
		);
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 9,
					'name'    => '<Live>',
					'slug'    => 'live',
				)
			),
			'https://example.com/event-tag/live/'
		);
		$presentation = ( new EventContextResolver() )->resolve_public( 71 );
		$fields       = new EventFieldRenderer();

		self::assertNotNull( $presentation );
		self::assertStringContainsString( '<h1 class="wpse-single-event-title" id="event-title">Summer &lt;Event&gt;</h1>', $fields->title( $presentation, 'h1', 'event-title' ) );
		self::assertStringContainsString( 'alt="Poster &lt;wide&gt;"', $fields->featured_image( $presentation ) );
		self::assertStringContainsString( 'wpse-event-image-link', $fields->featured_image( $presentation, 'large', true ) );
		self::assertStringContainsString( 'alt=""', $fields->featured_image( $presentation, 'thumbnail', false, 'decorative' ) );
		self::assertStringContainsString( 'Europe/Brussels (UTC+02:00)', $fields->date_time( $presentation ) );
		self::assertStringNotContainsString( 'wpse-event-label', $fields->date_time( $presentation, false ) );
		self::assertStringContainsString( 'When:', $fields->date_time( $presentation, true, 'When:' ) );
		self::assertStringContainsString( 'wpse-event-status-cancelled', $fields->status( $presentation ) );
		self::assertStringContainsString( 'Main Hall', $fields->venue( $presentation ) );
		self::assertStringContainsString( 'Place:', $fields->venue( $presentation, true, 'Place:' ) );
		self::assertStringContainsString( "High Street 1<br />\nBrussels", $fields->address( $presentation ) );
		self::assertStringContainsString(
			'<a href="https://example.com/route/" target="_blank" rel="noopener noreferrer">View location</a>',
			$fields->location_action( $presentation )
		);
		self::assertStringContainsString( 'Route plan', $fields->location_action( $presentation, 'Route plan' ) );
		self::assertSame( '<div class="wpse-single-event-content"><p>Full <strong>details</strong></p></div>', $fields->content( $presentation ) );
		self::assertStringContainsString( 'Short <strong>summary</strong>', $fields->excerpt( $presentation ) );
		self::assertStringContainsString(
			'<a class="wpse-event-action-link" href="https://example.com/register/" target="_blank" rel="noopener noreferrer">Register now</a>',
			$fields->external_action( $presentation )
		);
		self::assertStringContainsString( '>Programme</a>', $fields->external_action( $presentation, 'Programme' ) );
		self::assertStringContainsString( 'Music &amp; Arts', $fields->categories( $presentation ) );
		self::assertStringNotContainsString( 'wpse-event-label', $fields->categories( $presentation, false ) );
		self::assertStringContainsString( '&lt;Live&gt;', $fields->tags( $presentation ) );
		self::assertSame( EventStatus::CANCELLED, $presentation->status );
	}

	/**
	 * Empty and corrupt optional values never create wrappers or unsafe links.
	 */
	public function test_omits_empty_and_corrupt_optional_fields(): void {
		$event = new WP_Post(
			array(
				'ID'          => 72,
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => '',
			)
		);
		WordPressState::add_post( $event, 'https://example.com/events/empty/' );
		WordPressState::update_post_meta( 72, EventMeta::START_UTC, array( 'bad' ) );
		WordPressState::update_post_meta( 72, EventMeta::STATUS, 'invented' );
		WordPressState::update_post_meta( 72, EventMeta::VENUE, array( 'bad' ) );
		WordPressState::update_post_meta( 72, EventMeta::LOCATION_URL, 'javascript:alert(1)' );
		WordPressState::update_post_meta( 72, EventMeta::EVENT_URL, 'javascript:alert(1)' );
		$presentation = ( new EventContextResolver() )->resolve_public( 72 );
		$fields       = new EventFieldRenderer();

		self::assertNotNull( $presentation );
		self::assertStringContainsString( 'Untitled event', $fields->title( $presentation ) );
		self::assertSame( '', $fields->featured_image( $presentation ) );
		self::assertSame( '', $fields->date_time( $presentation ) );
		self::assertSame( '', $fields->status( $presentation ) );
		self::assertSame( '', $fields->venue( $presentation ) );
		self::assertSame( '', $fields->address( $presentation ) );
		self::assertSame( '', $fields->location_action( $presentation ) );
		self::assertSame( '', $fields->content( $presentation ) );
		self::assertSame( '', $fields->excerpt( $presentation ) );
		self::assertSame( '', $fields->external_action( $presentation ) );
		self::assertSame( '', $fields->categories( $presentation ) );
		self::assertSame( '', $fields->tags( $presentation ) );
	}

	/**
	 * Atomic fields never reveal a password-protected event.
	 */
	public function test_password_protection_hides_every_atomic_field(): void {
		$event = new WP_Post(
			array(
				'ID'            => 73,
				'post_type'     => EventPostType::POST_TYPE,
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_title'    => 'Hidden title',
				'post_excerpt'  => 'Hidden excerpt',
				'post_content'  => 'Hidden content',
			)
		);
		WordPressState::add_post( $event, 'https://example.com/events/hidden/', 'https://example.com/hidden.jpg', 'Hidden image' );
		$this->set_valid_meta( 73 );
		WordPressState::set_post_terms( 73, EventTaxonomies::CATEGORY, array( 18 ) );
		WordPressState::set_post_terms( 73, EventTaxonomies::TAG, array( 19 ) );
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 18,
					'name'    => 'Hidden category',
					'slug'    => 'hidden-category',
				)
			),
			'https://example.com/event-category/hidden/'
		);
		WordPressState::add_term(
			new WP_Term(
				array(
					'term_id' => 19,
					'name'    => 'Hidden tag',
					'slug'    => 'hidden-tag',
				)
			),
			'https://example.com/event-tag/hidden/'
		);
		$presentation = ( new EventContextResolver() )->resolve_current( 73 );
		$fields       = new EventFieldRenderer();

		self::assertNotNull( $presentation );
		self::assertSame( '', $fields->title( $presentation ) );
		self::assertSame( '', $fields->featured_image( $presentation ) );
		self::assertSame( '', $fields->date_time( $presentation ) );
		self::assertSame( '', $fields->status( $presentation ) );
		self::assertSame( '', $fields->venue( $presentation ) );
		self::assertSame( '', $fields->address( $presentation ) );
		self::assertSame( '', $fields->location_action( $presentation ) );
		self::assertSame( '', $fields->content( $presentation ) );
		self::assertSame( '', $fields->excerpt( $presentation ) );
		self::assertSame( '', $fields->external_action( $presentation ) );
		self::assertSame( '', $fields->categories( $presentation ) );
		self::assertSame( '', $fields->tags( $presentation ) );
	}

	/**
	 * Content filters cannot recursively render the same atomic content field.
	 */
	public function test_content_renderer_stops_recursive_content_filters(): void {
		$event = new WP_Post(
			array(
				'ID'           => 74,
				'post_type'    => EventPostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Recursive event',
				'post_content' => '<p>Once</p>',
			)
		);
		WordPressState::add_post( $event );
		$presentation = ( new EventContextResolver() )->resolve_public( 74 );
		$fields       = new EventFieldRenderer();
		self::assertNotNull( $presentation );
		WordPressState::set_filter(
			'the_content',
			static fn ( string $content ): string => $content . $fields->content( $presentation )
		);

		$output = $fields->content( $presentation );

		self::assertSame( 1, substr_count( $output, '<p>Once</p>' ) );
	}

	/**
	 * Set a complete, valid event metadata fixture.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function set_valid_meta( int $event_id ): void {
		$timezone = new DateTimeZone( 'Europe/Brussels' );
		$start    = ( new DateTimeImmutable( '2026-07-20 12:00:00', $timezone ) )->getTimestamp();
		$end      = ( new DateTimeImmutable( '2026-07-20 14:00:00', $timezone ) )->getTimestamp();

		WordPressState::update_post_meta( $event_id, EventMeta::START_UTC, $start );
		WordPressState::update_post_meta( $event_id, EventMeta::END_UTC, $end );
		WordPressState::update_post_meta( $event_id, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( $event_id, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( $event_id, EventMeta::STATUS, EventStatus::CANCELLED->value );
		WordPressState::update_post_meta( $event_id, EventMeta::VENUE, 'Main <b>Hall</b>' );
		WordPressState::update_post_meta( $event_id, EventMeta::ADDRESS, "High Street 1\nBrussels" );
		WordPressState::update_post_meta( $event_id, EventMeta::LOCATION_URL, 'https://example.com/route/' );
		WordPressState::update_post_meta( $event_id, EventMeta::EVENT_URL, 'https://example.com/register/' );
		WordPressState::update_post_meta( $event_id, EventMeta::EVENT_URL_LABEL, 'Register <b>now</b>' );
	}
}
