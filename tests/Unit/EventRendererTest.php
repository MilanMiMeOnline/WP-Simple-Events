<?php
/**
 * Tests for bounded semantic event cards.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventColorPresentation;
use MiMe\WPSimpleEvents\Domain\EventColorSource;
use MiMe\WPSimpleEvents\Domain\EventListView;
use MiMe\WPSimpleEvents\Frontend\EventCardOptions;
use MiMe\WPSimpleEvents\Frontend\EventListRenderer;
use MiMe\WPSimpleEvents\Frontend\EventRenderer;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( EventCardOptions::class )]
#[CoversClass( EventListRenderer::class )]
#[CoversClass( EventRenderer::class )]
/** Protects optional card content, heading semantics and external links. */
final class EventRendererTest extends TestCase {
	/** Reset the isolated WordPress test state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** Hidden title/date use an article label and location URLs stay isolated. */
	public function test_card_options_remain_semantic_and_bounded(): void {
		$event = new WP_Post(
			array(
				'ID'           => 701,
				'post_type'    => EventPostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Accessible event',
				'post_excerpt' => 'One two three four five',
			)
		);
		WordPressState::add_post( $event, 'https://example.test/events/accessible/' );
		WordPressState::update_post_meta( 701, EventMeta::START_UTC, 1_784_544_000 );
		WordPressState::update_post_meta( 701, EventMeta::END_UTC, 1_784_547_600 );
		WordPressState::update_post_meta( 701, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( 701, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( 701, EventMeta::VENUE, 'Event hall' );
		WordPressState::update_post_meta( 701, EventMeta::LOCATION_URL, 'https://example.test/route/' );

		$output = ( new EventRenderer() )->card(
			$event,
			new EventCardOptions( true, false, true, false, false, 3, 'h4' )
		);

		self::assertStringContainsString( 'aria-label="Accessible event"', $output );
		self::assertStringNotContainsString( '<time', $output );
		self::assertStringNotContainsString( 'wpse-event-card-title', $output );
		self::assertStringContainsString( 'One two three', $output );
		self::assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $output );
	}

	/** A visible title uses only the allowlisted heading element. */
	public function test_visible_title_uses_configured_heading(): void {
		$event = new WP_Post(
			array(
				'ID'          => 702,
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Heading event',
			)
		);
		WordPressState::add_post( $event, 'https://example.test/events/heading/' );
		WordPressState::update_post_meta( 702, EventMeta::START_UTC, 1_784_544_000 );
		WordPressState::update_post_meta( 702, EventMeta::END_UTC, 1_784_547_600 );
		WordPressState::update_post_meta( 702, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( 702, EventMeta::TIMEZONE, 'Europe/Brussels' );

		$output = ( new EventRenderer() )->card(
			$event,
			new EventCardOptions( false, false, false, true, true, 30, 'h4' )
		);

		self::assertStringContainsString( '<h4 class="wpse-event-card-title"', $output );
		self::assertStringContainsString( 'aria-labelledby="wpse-event-702-title"', $output );
	}

	/** The same event remains uniquely labelled across independent collections. */
	public function test_collection_scope_prevents_duplicate_card_title_ids(): void {
		$event = new WP_Post(
			array(
				'ID'          => 704,
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Repeated event',
			)
		);
		WordPressState::add_post( $event, 'https://example.test/events/repeated/' );
		WordPressState::update_post_meta( 704, EventMeta::START_UTC, 1_784_544_000 );
		WordPressState::update_post_meta( 704, EventMeta::END_UTC, 1_784_547_600 );
		WordPressState::update_post_meta( 704, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( 704, EventMeta::TIMEZONE, 'Europe/Brussels' );

		$renderer = new EventListRenderer();
		$options  = new EventCardOptions( false, false, false, true, true, 30, 'h3' );
		$first    = $renderer->render( array( $event ), EventListView::LIST, 1, $options, 'wpse-events-1-results' );
		$second   = $renderer->render( array( $event ), EventListView::LIST, 1, $options, 'wpse-events-2-results' );

		self::assertStringContainsString( 'id="wpse-event-704-wpse-events-1-results-title"', $first );
		self::assertStringContainsString( 'aria-labelledby="wpse-event-704-wpse-events-1-results-title"', $first );
		self::assertStringContainsString( 'id="wpse-event-704-wpse-events-2-results-title"', $second );
		self::assertStringContainsString( 'aria-labelledby="wpse-event-704-wpse-events-2-results-title"', $second );
	}

	/** No-JavaScript calendar cards expose only a normalized accent variable. */
	public function test_resolved_color_adds_a_text_backed_fallback_accent(): void {
		$event = new WP_Post(
			array(
				'ID'          => 703,
				'post_type'   => EventPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Colored event',
			)
		);
		WordPressState::add_post( $event, 'https://example.test/events/colored/' );
		WordPressState::update_post_meta( 703, EventMeta::START_UTC, 1_784_544_000 );
		WordPressState::update_post_meta( 703, EventMeta::END_UTC, 1_784_547_600 );
		WordPressState::update_post_meta( 703, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( 703, EventMeta::TIMEZONE, 'Europe/Brussels' );

		$output = ( new EventRenderer() )->card(
			$event,
			new EventCardOptions( false, false, false, true, true, 30, 'h3' ),
			new EventColorPresentation( '#336699', '#ffffff', EventColorSource::CUSTOM )
		);

		self::assertStringContainsString( 'wpse-event-card-has-color', $output );
		self::assertStringContainsString( 'style="--wpse-event-color:#336699"', $output );
		self::assertStringContainsString( 'Colored event', $output );
	}
}
