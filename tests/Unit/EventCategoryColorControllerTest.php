<?php
/**
 * Tests for secure event-category color editing.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventCategoryColorController;
use MiMe\WPSimpleEvents\Content\EventCategoryMeta;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Term;

#[CoversClass( EventCategoryColorController::class )]
/** Verifies nonce, capability, validation, deletion and escaped list presentation. */
final class EventCategoryColorControllerTest extends TestCase {
	/** Reset request and WordPress test state. */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
		$_POST = array();
	}

	/** Remove request state after every test. */
	protected function tearDown(): void {
		$_POST = array();
	}

	/** Category-specific hooks cover add/edit validation, persistence and list swatches. */
	public function test_registers_only_the_event_category_admin_hooks(): void {
		( new EventCategoryColorController() )->register();

		self::assertIsCallable( HookRecorder::action( EventTaxonomies::CATEGORY . '_add_form_fields' ) );
		self::assertIsCallable( HookRecorder::action( EventTaxonomies::CATEGORY . '_edit_form_fields' ) );
		self::assertIsCallable( HookRecorder::action( 'pre_insert_term' ) );
		self::assertIsCallable( HookRecorder::action( 'created_' . EventTaxonomies::CATEGORY ) );
		self::assertIsCallable( HookRecorder::action( 'edited_' . EventTaxonomies::CATEGORY ) );
		self::assertIsCallable( HookRecorder::action( 'manage_edit-' . EventTaxonomies::CATEGORY . '_columns' ) );
		self::assertIsCallable( HookRecorder::action( 'manage_' . EventTaxonomies::CATEGORY . '_custom_column' ) );
	}

	/** Add and edit forms expose an optional labelled native picker and plugin nonce. */
	public function test_renders_optional_accessible_add_and_edit_fields(): void {
		$controller = new EventCategoryColorController();

		ob_start();
		$controller->render_add_field();
		$add = ob_get_clean();
		self::assertIsString( $add );
		self::assertStringContainsString( 'name="wpse_event_category_color_nonce"', $add );
		self::assertStringContainsString( 'name="wpse_category_color_enabled"', $add );
		self::assertStringContainsString( 'type="color"', $add );

		WordPressState::update_term_meta( 7, EventCategoryMeta::COLOR, '#AABBCC' );
		ob_start();
		$controller->render_edit_field(
			new WP_Term(
				array(
					'term_id'  => 7,
					'taxonomy' => EventTaxonomies::CATEGORY,
				)
			)
		);
		$edit = ob_get_clean();
		self::assertIsString( $edit );
		self::assertStringContainsString( 'value="#aabbcc"', $edit );
		self::assertStringContainsString( 'checked="checked"', $edit );
	}

	/** Invalid enabled colors fail before WordPress writes the term. */
	public function test_rejects_invalid_or_unauthorized_color_submissions_before_term_write(): void {
		$controller = new EventCategoryColorController();
		$_POST      = $this->payload( 'red;display:none' );
		WordPressState::allow_current_user( true );

		$invalid = $controller->validate_submission( 'Music', EventTaxonomies::CATEGORY, array() );
		self::assertInstanceOf( WP_Error::class, $invalid );
		self::assertSame( 'wpse_invalid_category_color', $invalid->get_error_code() );

		$_POST = $this->payload( '#112233' );
		WordPressState::allow_current_user( false );
		$unauthorized = $controller->validate_submission( 'Music', EventTaxonomies::CATEGORY, array() );
		self::assertInstanceOf( WP_Error::class, $unauthorized );
		self::assertSame( 'wpse_category_color_forbidden', $unauthorized->get_error_code() );
	}

	/** Authorized saves normalize a color; disabling it deletes metadata. */
	public function test_saves_and_deletes_only_after_nonce_and_capability_checks(): void {
		$controller = new EventCategoryColorController();
		WordPressState::allow_current_user( true );
		$_POST = $this->payload( '#AABBCC' );

		$controller->save( 9 );
		self::assertSame( '#aabbcc', WordPressState::term_meta( 9, EventCategoryMeta::COLOR ) );

		$_POST = $this->payload( '#aabbcc', false );
		$controller->save( 9 );
		self::assertFalse( WordPressState::has_term_meta( 9, EventCategoryMeta::COLOR ) );

		$_POST = $this->payload( '#ffffff' );
		$_POST[ EventCategoryColorController::NONCE_NAME ] = 'invalid';
		$controller->save( 9 );
		self::assertFalse( WordPressState::has_term_meta( 9, EventCategoryMeta::COLOR ) );
	}

	/** The term list adds text plus a bounded swatch and never arbitrary CSS. */
	public function test_adds_a_labelled_normalized_term_list_swatch(): void {
		$controller = new EventCategoryColorController();
		$columns    = $controller->columns( array( 'name' => 'Name' ) );
		self::assertSame( 'Color', $columns['wpse_color'] ?? null );

		WordPressState::update_term_meta( 5, EventCategoryMeta::COLOR, '#AABBCC' );
		$html = $controller->column( '', 'wpse_color', 5 );
		self::assertStringContainsString( 'background-color:#aabbcc', $html );
		self::assertStringContainsString( '#aabbcc', $html );

		WordPressState::update_term_meta( 5, EventCategoryMeta::COLOR, 'red;display:none' );
		self::assertSame( '—', $controller->column( '', 'wpse_color', 5 ) );
	}

	/**
	 * Build one deterministic taxonomy form submission.
	 *
	 * @param string $color   Submitted color.
	 * @param bool   $enabled Whether the optional color is enabled.
	 */
	private function payload( string $color, bool $enabled = true ): array {
		$payload = array(
			EventCategoryColorController::NONCE_NAME => 'valid-category-color-nonce',
			'wpse_category_color'                    => $color,
		);

		if ( $enabled ) {
			$payload['wpse_category_color_enabled'] = '1';
		}

		return $payload;
	}
}
