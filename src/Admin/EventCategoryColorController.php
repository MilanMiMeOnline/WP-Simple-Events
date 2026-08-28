<?php
/**
 * Secure event-category color editor.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Access\EventCapabilities;
use MiMe\WPSimpleEvents\Content\EventCategoryMeta;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\HexColor;
use WP_Error;
use WP_Term;

/** Adds optional category colors without accepting arbitrary CSS. */
final class EventCategoryColorController {
	public const NONCE_ACTION = 'wpse_save_event_category_color';
	public const NONCE_NAME   = 'wpse_event_category_color_nonce';

	private const COLOR_FIELD   = 'wpse_category_color';
	private const ENABLED_FIELD = 'wpse_category_color_enabled';
	private const COLUMN        = 'wpse_color';
	private const DEFAULT_COLOR = '#2271b1';

	/** Register category-form, persistence and list-table hooks. */
	public function register(): void {
		add_action( EventTaxonomies::CATEGORY . '_add_form_fields', array( $this, 'render_add_field' ) );
		add_action( EventTaxonomies::CATEGORY . '_edit_form_fields', array( $this, 'render_edit_field' ) );
		add_filter( 'pre_insert_term', array( $this, 'validate_submission' ), 10, 3 );
		add_action( 'created_' . EventTaxonomies::CATEGORY, array( $this, 'save' ) );
		add_action( 'edited_' . EventTaxonomies::CATEGORY, array( $this, 'save' ) );
		add_filter( 'manage_edit-' . EventTaxonomies::CATEGORY . '_columns', array( $this, 'columns' ) );
		add_filter( 'manage_' . EventTaxonomies::CATEGORY . '_custom_column', array( $this, 'column' ), 10, 3 );
	}

	/** Render the color control on the category creation form. */
	public function render_add_field(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="form-field term-wpse-color-wrap">
			<?php $this->render_control( '', false ); ?>
		</div>
		<?php
	}

	/**
	 * Render the color control on the category edit form.
	 *
	 * @param WP_Term $term Event category being edited.
	 */
	public function render_edit_field( WP_Term $term ): void {
		$color = HexColor::normalize( get_term_meta( $term->term_id, EventCategoryMeta::COLOR, true ) );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<tr class="form-field term-wpse-color-wrap">
			<th scope="row"><label for="wpse-category-color"><?php esc_html_e( 'Calendar color', 'mime-simple-events-calendar' ); ?></label></th>
			<td><?php $this->render_control( $color, '' !== $color, false ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Reject an invalid enabled color before WordPress writes the term.
	 *
	 * @param string|WP_Error     $term     Proposed term name or earlier error.
	 * @param string              $taxonomy Target taxonomy.
	 * @param array<string,mixed> $args     Term arguments.
	 * @return string|WP_Error
	 */
	public function validate_submission( string|WP_Error $term, string $taxonomy, array $args ): string|WP_Error {
		unset( $args );

		if ( $term instanceof WP_Error || EventTaxonomies::CATEGORY !== $taxonomy || ! $this->has_submission() ) {
			return $term;
		}

		if ( ! $this->verified_nonce() ) {
			return new WP_Error(
				'wpse_category_color_nonce',
				__( 'The event category color could not be verified. Please try again.', 'mime-simple-events-calendar' )
			);
		}

		if ( ! current_user_can( EventCapabilities::MANAGE_TERMS ) ) {
			return new WP_Error(
				'wpse_category_color_forbidden',
				__( 'You are not allowed to change event category colors.', 'mime-simple-events-calendar' )
			);
		}

		if ( $this->enabled() && '' === HexColor::normalize( $this->submitted_color() ) ) {
			return new WP_Error(
				'wpse_invalid_category_color',
				__( 'Choose a valid six-digit event category color.', 'mime-simple-events-calendar' )
			);
		}

		return $term;
	}

	/**
	 * Save or delete one verified category color.
	 *
	 * @param int $term_id Event category ID.
	 */
	public function save( int $term_id ): void {
		if ( $term_id <= 0
			|| ! $this->has_submission()
			|| ! $this->verified_nonce()
			|| ! current_user_can( EventCapabilities::MANAGE_TERMS )
		) {
			return;
		}

		if ( ! $this->enabled() ) {
			delete_term_meta( $term_id, EventCategoryMeta::COLOR );
			return;
		}

		$color = HexColor::normalize( $this->submitted_color() );

		if ( '' !== $color ) {
			update_term_meta( $term_id, EventCategoryMeta::COLOR, $color );
		}
	}

	/**
	 * Add one category-list color column.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function columns( array $columns ): array {
		$columns[ self::COLUMN ] = __( 'Color', 'mime-simple-events-calendar' );

		return $columns;
	}

	/**
	 * Render one normalized, labelled category-list swatch.
	 *
	 * @param string $output      Existing output.
	 * @param string $column_name Current column.
	 * @param int    $term_id     Event category ID.
	 */
	public function column( string $output, string $column_name, int $term_id ): string {
		if ( self::COLUMN !== $column_name ) {
			return $output;
		}

		$color = HexColor::normalize( get_term_meta( $term_id, EventCategoryMeta::COLOR, true ) );

		if ( '' === $color ) {
			return '—';
		}

		return sprintf(
			'<span class="wpse-category-color-swatch" aria-hidden="true" style="display:inline-block;width:1.25em;height:1.25em;margin-right:.5em;vertical-align:middle;border:1px solid currentColor;border-radius:2px;background-color:%1$s"></span><code>%1$s</code>',
			esc_attr( $color )
		);
	}

	/**
	 * Render the shared optional native color control.
	 *
	 * @param string $color      Normalized stored color.
	 * @param bool   $enabled    Whether the optional color is enabled.
	 * @param bool   $with_label Whether this layout needs its inline label.
	 */
	private function render_control( string $color, bool $enabled, bool $with_label = true ): void {
		$value = '' !== $color ? $color : self::DEFAULT_COLOR;
		?>
		<?php if ( $with_label ) : ?>
			<label for="wpse-category-color"><?php esc_html_e( 'Calendar color', 'mime-simple-events-calendar' ); ?></label>
		<?php endif; ?>
		<label class="wpse-category-color-enabled">
			<input type="checkbox" name="<?php echo esc_attr( self::ENABLED_FIELD ); ?>" value="1" <?php checked( $enabled ); ?>>
			<?php esc_html_e( 'Use this color for events in this category', 'mime-simple-events-calendar' ); ?>
		</label>
		<input type="color" id="wpse-category-color" name="<?php echo esc_attr( self::COLOR_FIELD ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<p class="description"><?php esc_html_e( 'Optional. Events can use this color automatically or select it explicitly. Public text color is chosen automatically for contrast.', 'mime-simple-events-calendar' ); ?></p>
		<?php
	}

	/** Whether this request carries the plugin-owned form boundary. */
	private function has_submission(): bool {
		return isset( $_POST[ self::NONCE_NAME ] ) || isset( $_POST[ self::COLOR_FIELD ] ) || isset( $_POST[ self::ENABLED_FIELD ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only detects a candidate submission; verification precedes every state decision.
	}

	/** Verify the plugin nonce without trusting non-scalar request data. */
	private function verified_nonce(): bool {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! is_string( $_POST[ self::NONCE_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence is checked before verification.
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The value is verified on the next line.

		return false !== wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/** Whether the optional color was explicitly enabled. */
	private function enabled(): bool {
		return isset( $_POST[ self::ENABLED_FIELD ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the nonce before using the value.
			&& is_scalar( $_POST[ self::ENABLED_FIELD ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the nonce before using the value.
			&& '1' === sanitize_text_field( wp_unslash( (string) $_POST[ self::ENABLED_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the nonce before using the value.
	}

	/** Read one scalar color candidate after the caller verifies the nonce. */
	private function submitted_color(): string {
		if ( ! isset( $_POST[ self::COLOR_FIELD ] ) || ! is_scalar( $_POST[ self::COLOR_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the nonce before using the value.
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $_POST[ self::COLOR_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the nonce before using the value.
	}
}
