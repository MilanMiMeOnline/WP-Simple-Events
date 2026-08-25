<?php
/**
 * Tests for native event editor schedule ownership.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventMetaBox;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Application\RecurrenceScheduleOwnership;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Verifies that the ordinary metabox never presents a second recurrence editor.
 */
#[CoversClass( EventMetaBox::class )]
#[CoversClass( RecurrenceScheduleOwnership::class )]
final class EventMetaBoxTest extends TestCase {
	/** Reset deterministic metadata before every test. */
	protected function setUp(): void {
		WordPressState::reset();
		$this->store_event_schedule();
	}

	/** A one-off event retains its ordinary editable schedule controls. */
	public function test_one_off_schedule_controls_are_visible_and_enabled(): void {
		$html = $this->render();

		self::assertStringContainsString( 'data-wpse-schedule-owner="event"', $html );
		self::assertMatchesRegularExpression( '/data-wpse-schedule-fields\s*>/', $html );
		self::assertMatchesRegularExpression( '/data-wpse-recurrence-schedule-notice[^>]*hidden/', $html );
		self::assertStringNotContainsString( 'data-wpse-schedule-shadow', $html );
		self::assertDoesNotMatchRegularExpression( '/id="wpse-start-date"[^>]*disabled/', $html );
	}

	/** A recurrence-owned event exposes one accurate notice and no second schedule editor. */
	public function test_recurring_schedule_is_replaced_by_notice_and_safe_shadow_values(): void {
		WordPressState::update_post_meta( 42, EventMeta::RECURRENCE, '{"protected":"aggregate"}' );

		$html = $this->render();

		self::assertStringContainsString( 'data-wpse-schedule-owner="recurrence"', $html );
		self::assertMatchesRegularExpression( '/data-wpse-schedule-fields\s+hidden/', $html );
		self::assertStringContainsString( 'This event’s schedule is managed in the block editor.', $html );
		self::assertStringNotContainsString( 'This event repeats.', $html );
		self::assertStringContainsString( 'Repeating event panel in the block editor', $html );
		self::assertMatchesRegularExpression( '/data-wpse-schedule-intro[^>]*hidden/', $html );
		self::assertStringContainsString( 'id="wpse-start-date"', $html );
		self::assertMatchesRegularExpression( '/id="wpse-start-date"[^>]*disabled/', $html );
		self::assertStringContainsString( 'data-wpse-schedule-shadow', $html );
		self::assertStringContainsString( 'name="wpse_event[start_date]" value="2027-02-03"', $html );
		self::assertStringContainsString( 'id="wpse-status" name="wpse_event[status]"', $html );
	}

	/** Corrupt protected state remains recurrence-owned and cannot unlock the form. */
	public function test_non_empty_corrupt_recurrence_state_fails_safe(): void {
		WordPressState::update_post_meta( 42, EventMeta::RECURRENCE, array( 'unexpected' => 'shape' ) );

		self::assertStringContainsString(
			'data-wpse-schedule-owner="recurrence"',
			$this->render()
		);
	}

	/** Store the canonical one-off bootstrap schedule used by all cases. */
	private function store_event_schedule(): void {
		WordPressState::update_post_meta( 42, EventMeta::START_LOCAL, '2027-02-03T18:30:00' );
		WordPressState::update_post_meta( 42, EventMeta::END_LOCAL, '2027-02-03T21:00:00' );
		WordPressState::update_post_meta( 42, EventMeta::ALL_DAY, false );
		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::update_post_meta( 42, EventMeta::STATUS, 'scheduled' );
	}

	/** Render the metabox for the deterministic event. */
	private function render(): string {
		ob_start();
		( new EventMetaBox() )->render( new WP_Post( array( 'ID' => 42 ) ) );
		$html = ob_get_clean();

		self::assertIsString( $html );

		return $html;
	}
}
