<?php
/**
 * Gutenberg recurrence editor assets.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionWindowPolicy;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Loads the authenticated scope-first recurrence panel only for event editors.
 */
final class RecurrenceEditorAssets {
	public const SCRIPT_HANDLE = 'wpse-recurrence-editor';
	public const HORIZON_DAYS  = OccurrenceProjectionWindowPolicy::FRESH_DAYS;

	/** Register the block-editor asset hook. */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/** Load the editor adapter only while editing the event post type. */
	public function enqueue(): void {
		$screen = get_current_screen();

		if ( null === $screen || EventPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$start_of_week = (int) get_option( 'start_of_week', 1 );

		if ( $start_of_week < 0 || $start_of_week > 6 ) {
			$start_of_week = 1;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			plugin_dir_url( WPSE_PLUGIN_FILE ) . 'assets/dist/js/recurrence-editor.min.js',
			array( 'wp-api-fetch', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			WPSE_VERSION,
			true
		);
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wpseRecurrenceEditor',
			array(
				'eventPostType'  => EventPostType::POST_TYPE,
				'horizonDays'    => self::HORIZON_DAYS,
				'maxRows'        => RecurrenceGenerationWindow::MAX_ROWS,
				'startOfWeek'    => $start_of_week,
				'today'          => wp_date( 'Y-m-d' ),
				'overrideLimits' => array(
					'title'         => OccurrenceOverride::MAX_TITLE_LENGTH,
					'note'          => OccurrenceOverride::MAX_NOTE_LENGTH,
					'venue'         => OccurrenceOverride::MAX_VENUE_LENGTH,
					'address'       => OccurrenceOverride::MAX_ADDRESS_LENGTH,
					'url'           => OccurrenceOverride::MAX_URL_LENGTH,
					'eventUrlLabel' => OccurrenceOverride::MAX_LABEL_LENGTH,
				),
			)
		);
		wp_enqueue_media();
		wp_set_script_translations( self::SCRIPT_HANDLE, 'mime-simple-events-calendar', WPSE_PLUGIN_DIR . '/languages' );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}
}
