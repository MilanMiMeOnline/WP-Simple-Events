<?php
/**
 * Tests for event metadata boundary callbacks.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies allowlists, maximum lengths and authorization boundaries.
 */
#[CoversClass( EventMetaSanitizer::class )]
final class EventMetaSanitizerTest extends TestCase {
	/**
	 * Sanitizer under test.
	 *
	 * @var EventMetaSanitizer
	 */
	private EventMetaSanitizer $sanitizer;

	/**
	 * Reset WordPress state and create the sanitizer under test.
	 */
	protected function setUp(): void {
		WordPressState::reset();
		$this->sanitizer = new EventMetaSanitizer();
	}

	/**
	 * Valid local formats are normalized while impossible dates are rejected.
	 */
	public function test_local_datetime_is_strictly_normalized(): void {
		self::assertSame( '2026-07-01', $this->sanitizer->local_datetime( '2026-07-01' ) );
		self::assertSame( '2026-07-01T09:30:00', $this->sanitizer->local_datetime( '2026-07-01 09:30' ) );
		self::assertSame( '', $this->sanitizer->local_datetime( '2026-02-30' ) );
		self::assertSame( '', $this->sanitizer->local_datetime( array( '2026-07-01' ) ) );
	}

	/** Coverage metadata accepts dates only, never date-times or impossible dates. */
	public function test_local_date_is_strictly_normalized(): void {
		self::assertSame( '2028-02-29', $this->sanitizer->local_date( '2028-02-29' ) );
		self::assertSame( '', $this->sanitizer->local_date( '2027-02-29' ) );
		self::assertSame( '', $this->sanitizer->local_date( '2028-02-29T10:00:00' ) );
	}

	/**
	 * Event status uses an explicit allowlist and safe fallback.
	 */
	public function test_status_uses_allowlist(): void {
		self::assertSame( EventStatus::CANCELLED->value, $this->sanitizer->status( 'cancelled' ) );
		self::assertSame( EventStatus::SCHEDULED->value, $this->sanitizer->status( 'published' ) );
	}

	/**
	 * Only HTTP(S) external URLs are stored.
	 */
	public function test_url_rejects_unsafe_protocols(): void {
		self::assertSame( 'https://example.com/route', $this->sanitizer->url( 'https://example.com/route' ) );
		self::assertSame( '', $this->sanitizer->url( 'javascript:alert(1)' ) );
		self::assertSame( '', $this->sanitizer->url( 'mailto:events@example.com' ) );
	}

	/**
	 * IANA and valid WordPress offsets are accepted but arbitrary values are not.
	 */
	public function test_timezone_uses_strict_allowlist(): void {
		self::assertSame( 'Europe/Brussels', $this->sanitizer->timezone( 'Europe/Brussels' ) );
		self::assertSame( '+14:00', $this->sanitizer->timezone( '+14:00' ) );
		self::assertSame( '', $this->sanitizer->timezone( '+14:30' ) );
		self::assertSame( '', $this->sanitizer->timezone( '../../etc/passwd' ) );
	}

	/**
	 * Text fields are bounded after sanitization.
	 */
	public function test_venue_has_a_maximum_length(): void {
		self::assertSame( 200, strlen( $this->sanitizer->venue( str_repeat( 'a', 250 ) ) ) );
	}

	/**
	 * External action labels are plain text, bounded and reject structured input.
	 */
	public function test_event_url_label_is_plain_bounded_scalar_text(): void {
		self::assertSame(
			'Register now',
			$this->sanitizer->event_url_label( '<b>Register</b> <script>alert(1)</script> now' )
		);
		self::assertSame(
			EventMetaSanitizer::EVENT_URL_LABEL_MAX_LENGTH,
			strlen( $this->sanitizer->event_url_label( str_repeat( 'a', 140 ) ) )
		);
		self::assertSame( '', $this->sanitizer->event_url_label( array( 'Register' ) ) );
	}

	/**
	 * Boolean strings follow WordPress REST semantics.
	 */
	public function test_boolean_matches_rest_semantics(): void {
		self::assertFalse( $this->sanitizer->boolean( 'false' ) );
		self::assertFalse( $this->sanitizer->boolean( 0 ) );
		self::assertTrue( $this->sanitizer->boolean( 'yes' ) );
		self::assertFalse( $this->sanitizer->boolean( array() ) );
	}

	/**
	 * Internal series and generation identities reject malformed input.
	 */
	public function test_occurrence_identity_metadata_is_strict(): void {
		self::assertSame(
			'a28e5d8c-5237-4b02-97a4-3f8855a3d5ad',
			$this->sanitizer->uuid( 'A28E5D8C-5237-4B02-97A4-3F8855A3D5AD' )
		);
		self::assertSame( '', $this->sanitizer->uuid( 'not-a-uuid' ) );
		self::assertSame( 73, $this->sanitizer->generation( '73' ) );
		self::assertSame( 0, $this->sanitizer->generation( '-1' ) );
		self::assertSame( 0, $this->sanitizer->generation( 'arbitrary' ) );
	}

	/** Event color metadata accepts only its strict values and ID bounds. */
	public function test_event_color_metadata_is_strict_and_bounded(): void {
		self::assertSame( '#aabbcc', $this->sanitizer->color( '#AABBCC' ) );
		self::assertSame( '', $this->sanitizer->color( 'red;display:none' ) );
		self::assertSame( EventColorMode::CATEGORY->value, $this->sanitizer->color_mode( 'category' ) );
		self::assertSame( EventColorMode::FALLBACK->value, $this->sanitizer->color_mode( 'unexpected' ) );
		self::assertSame( 42, $this->sanitizer->term_id( '42' ) );
		self::assertSame( 0, $this->sanitizer->term_id( '-1' ) );
		self::assertSame( 0, $this->sanitizer->term_id( '9999999999999999' ) );
	}

	/**
	 * Metadata mutation always delegates to the event edit capability.
	 */
	public function test_authorization_requires_current_event_edit_access(): void {
		WordPressState::allow_current_user( false );
		self::assertFalse( $this->sanitizer->authorize( true, '_wpse_venue', 42 ) );

		WordPressState::allow_current_user( true );
		self::assertTrue( $this->sanitizer->authorize( false, '_wpse_venue', 42 ) );
	}
}
