<?php
/**
 * Tests for permission-safe Divi Visual Builder data.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Divi\DiviEditorDataProvider;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Query\PublicEventOptions;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( DiviEditorDataProvider::class )]
/** Prevents current editorial context from leaking into an unauthorized app window. */
final class DiviEditorDataProviderTest extends TestCase {
	/** Add one password-protected current event before each assertion. */
	protected function setUp(): void {
		WordPressState::reset();
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => 401,
					'post_type'     => EventPostType::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => 'secret',
					'post_title'    => 'Protected builder event',
				)
			),
			'https://example.com/events/protected-builder-event/'
		);
		WordPressState::update_post_meta( 401, EventMeta::VENUE, 'Editorial venue' );
		WordPressState::set_singular_event( true, 401 );
	}

	/** An unauthorized request receives neither current data nor an editable post ID. */
	public function test_current_editor_context_requires_the_exact_edit_capability(): void {
		$data = $this->provider()->data();

		self::assertNull( $data['current'] );
		self::assertSame( 0, $data['editorPostId'] );
	}

	/** An authorized editor receives the current context needed by native modules. */
	public function test_authorized_current_editor_context_remains_available(): void {
		WordPressState::allow_current_user( true );
		$data = $this->provider()->data();

		self::assertIsArray( $data['current'] );
		self::assertSame( 401, $data['editorPostId'] );
		self::assertSame( 'Protected builder event', $data['current']['title'] );
		self::assertSame( 'Editorial venue', $data['current']['venue'] );
	}

	/** Build the production data boundary with an empty public choice catalogue. */
	private function provider(): DiviEditorDataProvider {
		$contexts = new EventContextResolver();

		return new DiviEditorDataProvider(
			new PublicEventOptions(),
			$contexts,
			new CurrentEventPresentationResolver( $contexts )
		);
	}
}
