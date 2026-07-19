<?php
/**
 * Tests for event input adapters.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\EventInputMapper;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies native and REST inputs preserve the external action-label contract.
 */
#[CoversClass( EventInputMapper::class )]
final class EventInputMapperTest extends TestCase {
	/**
	 * Reset deterministic metadata state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Native input accepts a scalar action label and rejects structured values.
	 */
	public function test_admin_maps_only_scalar_event_url_labels(): void {
		$mapper = new EventInputMapper();

		self::assertSame(
			'Parking plan',
			$mapper->from_admin( array( 'event_url_label' => 'Parking plan' ), 0 )->event_url_label
		);
		self::assertSame(
			'',
			$mapper->from_admin( array( 'event_url_label' => array( 'Parking plan' ) ), 0 )->event_url_label
		);
	}

	/**
	 * Partial REST updates retain stored labels while legacy events default empty.
	 */
	public function test_rest_retains_stored_label_and_supports_existing_events_without_key(): void {
		$mapper = new EventInputMapper();
		WordPressState::update_post_meta( 42, EventMeta::EVENT_URL_LABEL, 'Registration' );

		self::assertSame( 'Registration', $mapper->from_rest( array(), 42 )->event_url_label );
		self::assertSame( '', $mapper->from_rest( array(), 43 )->event_url_label );
		self::assertSame(
			'',
			$mapper->from_rest( array( EventMeta::EVENT_URL_LABEL => array( 'unsafe' ) ), 42 )->event_url_label
		);
	}

	/**
	 * New events capture the current site zone while existing events retain theirs.
	 */
	public function test_admin_captures_site_timezone_only_for_new_events(): void {
		$mapper = new EventInputMapper();
		WordPressState::set_option( 'timezone_string', '' );
		WordPressState::set_option( 'gmt_offset', 5.5 );

		self::assertSame( '+05:30', $mapper->from_admin( array(), 0 )->timezone );

		WordPressState::update_post_meta( 42, EventMeta::TIMEZONE, 'Europe/Brussels' );
		WordPressState::set_option( 'timezone_string', 'Asia/Tokyo' );

		self::assertSame( 'Europe/Brussels', $mapper->from_admin( array(), 42 )->timezone );
	}
}
