<?php
/**
 * Tests for the atomic Gutenberg add-to-calendar block.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Blocks\AddToCalendarBlockRenderer;
use MiMe\WPSimpleEvents\Blocks\AddToCalendarBlockSettings;
use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarRenderer;
use MiMe\WPSimpleEvents\CalendarExport\CalendarExportSnapshot;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Tests\Support\FakeCalendarExportSnapshotProvider;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Block;
use WP_Post;

/** Protects source selection, presentation controls and bounded block styles. */
#[CoversClass( AddToCalendarBlockRenderer::class )]
#[CoversClass( AddToCalendarBlockSettings::class )]
final class AddToCalendarBlockTest extends TestCase {
	/** Reset one current public event. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 821,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => 'Calendar action event',
				)
			)
		);
	}

	/** Explicit and current event sources both delegate to the shared renderer. */
	public function test_renders_explicit_and_current_sources_without_duplicating_provider_logic(): void {
		$snapshots           = new FakeCalendarExportSnapshotProvider();
		$snapshots->snapshot = $this->snapshot();
		$renderer            = new AddToCalendarBlockRenderer( new AddToCalendarRenderer( $snapshots ) );
		$block               = new WP_Block(
			array( 'blockName' => 'wpse/add-to-calendar' ),
			array(
				'postId'   => 821,
				'postType' => EventPostType::POST_TYPE,
			)
		);
		$attributes          = array(
			'providers' => array( 'outlook', 'ics', 'google' ),
			'layout'    => 'list',
			'label'     => 'Save <b>this</b> event',
		);

		$current  = $renderer->render( $attributes, '', $block );
		$explicit = $renderer->render(
			array(
				...$attributes,
				'eventId' => 821,
			),
			'',
			$block
		);

		foreach ( array( $current, $explicit ) as $output ) {
			self::assertStringContainsString( 'wpse-add-to-calendar-block', $output );
			self::assertStringContainsString( 'Save this event', $output );
			self::assertStringContainsString( 'wpse-add-to-calendar-ics', $output );
			self::assertStringContainsString( 'wpse-add-to-calendar-google', $output );
			self::assertStringContainsString( 'wpse-add-to-calendar-outlook', $output );
		}

		self::assertSame(
			array(
				array(
					'event_id'   => 821,
					'public_key' => null,
				),
				array(
					'event_id'   => 821,
					'public_key' => null,
				),
			),
			$snapshots->requests
		);
	}

	/** Invalid explicit/context sources and empty provider intent fail closed. */
	public function test_rejects_invalid_sources_without_fallback(): void {
		$snapshots           = new FakeCalendarExportSnapshotProvider();
		$snapshots->snapshot = $this->snapshot();
		$renderer            = new AddToCalendarBlockRenderer( new AddToCalendarRenderer( $snapshots ) );
		$current             = new WP_Block(
			array( 'blockName' => 'wpse/add-to-calendar' ),
			array(
				'postId'   => 821,
				'postType' => EventPostType::POST_TYPE,
			)
		);

		self::assertSame( '', $renderer->render( array( 'eventId' => '821' ), '', $current ) );
		self::assertSame(
			'',
			$renderer->render(
				array(),
				'',
				new WP_Block(
					array( 'blockName' => 'wpse/add-to-calendar' ),
					array(
						'postId'   => 821,
						'postType' => 'post',
					)
				)
			)
		);
		self::assertSame( '', $renderer->render( array( 'providers' => array() ), '', $current ) );
		self::assertSame( array(), $snapshots->requests );
	}

	/** Visual attributes accept only strict colors and bounded integer pixels. */
	public function test_emits_only_bounded_custom_properties(): void {
		$snapshots           = new FakeCalendarExportSnapshotProvider();
		$snapshots->snapshot = $this->snapshot();
		$renderer            = new AddToCalendarBlockRenderer( new AddToCalendarRenderer( $snapshots ) );
		$output              = $renderer->render(
			array(
				'eventId'             => 821,
				'actionBackground'    => '#AABBCC',
				'actionText'          => 'red;display:none',
				'actionRadius'        => 12,
				'actionGap'           => 101,
				'actionPaddingInline' => 18,
			),
			'',
			new WP_Block( array( 'blockName' => 'wpse/add-to-calendar' ) )
		);

		self::assertStringContainsString( '--wpse-calendar-action-background:#aabbcc', $output );
		self::assertStringContainsString( '--wpse-calendar-action-radius:12px', $output );
		self::assertStringContainsString( '--wpse-calendar-action-padding-inline:18px', $output );
		self::assertStringNotContainsString( 'display:none', $output );
		self::assertStringNotContainsString( '--wpse-calendar-action-gap', $output );
		self::assertSame( '', AddToCalendarBlockSettings::color( '#abc' ) );
		self::assertNull( AddToCalendarBlockSettings::integer( '12', 0, 100 ) );
	}

	/** Block metadata freezes the editor/server contract. */
	public function test_metadata_matches_the_dynamic_block_contract(): void {
		$metadata = json_decode(
			(string) file_get_contents( dirname( __DIR__, 2 ) . '/blocks/add-to-calendar/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads one trusted local test fixture.
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		self::assertSame( 'wpse/add-to-calendar', $metadata['name'] ?? null );
		self::assertSame( array( 'ics' ), $metadata['attributes']['providers']['default'] ?? null );
		self::assertSame( array( 'postId', 'postType' ), $metadata['usesContext'] ?? null );
		self::assertSame( 'wpse-event-fields-editor', $metadata['editorScript'] ?? null );
		self::assertFalse( $metadata['supports']['html'] ?? true );
	}

	/** Return one deterministic public snapshot. */
	private function snapshot(): CalendarExportSnapshot {
		return new CalendarExportSnapshot(
			821,
			OccurrenceIdentity::from( '019c1d83-1798-4fac-a66d-ae8d67c46319', 'one-off' ),
			'Concert',
			'https://example.com/events/concert/',
			EventDateRange::from_local( '2026-07-16T19:30:00', '2026-07-16T21:30:00', false, 'Europe/Brussels' ),
			EventStatus::SCHEDULED,
			'Details.',
			'Town Hall',
			1_784_220_000,
			'concert-2026-07-16'
		);
	}
}
