<?php
/**
 * Native event details meta box.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Application\EventInput;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use WP_Post;

/**
 * Renders accessible native controls for the event metadata contract.
 */
final class EventMetaBox {
	public const NONCE_ACTION = 'wpse_save_event';
	public const NONCE_NAME   = 'wpse_event_nonce';

	/**
	 * Register editor hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . EventPostType::POST_TYPE, array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the event details meta box.
	 */
	public function add(): void {
		add_meta_box(
			'wpse-event-details',
			__( 'Event details', 'simple-events-by-mime' ),
			array( $this, 'render' ),
			EventPostType::POST_TYPE,
			'normal',
			'high',
			array(
				'__block_editor_compatible_meta_box' => true,
			)
		);
	}

	/**
	 * Render event editor fields.
	 *
	 * @param WP_Post $post Current event post.
	 */
	public function render( WP_Post $post ): void {
		$input = $this->stored_input( $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="wpse-event-fields" data-wpse-event-fields>
			<p class="wpse-event-fields-intro">
				<?php esc_html_e( 'A start is required before an event can be published. Drafts may remain incomplete.', 'simple-events-by-mime' ); ?>
			</p>

			<?php if ( $this->dates_need_review( $post->ID ) ) : ?>
				<div class="notice notice-warning inline wpse-event-date-review" role="status">
					<p><strong><?php esc_html_e( 'Review the copied start and end date before publishing this event.', 'simple-events-by-mime' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<p class="wpse-event-fields-all-day">
				<label for="wpse-all-day">
					<input type="checkbox" id="wpse-all-day" name="wpse_event[all_day]" value="1" <?php checked( $input->all_day ); ?>>
					<?php esc_html_e( 'All-day event', 'simple-events-by-mime' ); ?>
				</label>
			</p>

			<div class="wpse-event-fields-grid">
				<?php $this->render_input( 'start-date', 'start_date', __( 'Start date', 'simple-events-by-mime' ), 'date', $input->start_date ); ?>
				<div data-wpse-time-field>
					<?php $this->render_input( 'start-time', 'start_time', __( 'Start time', 'simple-events-by-mime' ), 'time', $input->start_time, '60' ); ?>
				</div>
				<?php $this->render_input( 'end-date', 'end_date', __( 'End date', 'simple-events-by-mime' ), 'date', $input->end_date ); ?>
				<div data-wpse-time-field>
					<?php $this->render_input( 'end-time', 'end_time', __( 'End time', 'simple-events-by-mime' ), 'time', $input->end_time, '60' ); ?>
				</div>
			</div>

			<p class="description wpse-event-fields-timezone">
				<?php
				printf(
					/* translators: %s: Event timezone identifier. */
					esc_html__( 'Timezone: %s. Existing events keep their saved timezone.', 'simple-events-by-mime' ),
					esc_html( $input->timezone )
				);
				?>
			</p>
			<p class="description wpse-event-fields-time-format">
				<?php esc_html_e( 'Time controls may look different across browsers. Events are saved with the same canonical 24-hour value; public output follows the WordPress time format.', 'simple-events-by-mime' ); ?>
			</p>

			<div class="wpse-event-fields-grid">
				<?php $this->render_input( 'venue', 'venue', __( 'Venue', 'simple-events-by-mime' ), 'text', $input->venue, null, 200 ); ?>
				<?php $this->render_status( $input->status ); ?>
			</div>

			<?php $this->render_textarea( 'address', 'address', __( 'Address', 'simple-events-by-mime' ), $input->address, 500 ); ?>

			<?php $this->render_input( 'location-url', 'location_url', __( 'Location URL', 'simple-events-by-mime' ), 'url', $input->location_url, null, 2048, __( 'Optional route or location page using HTTP(S).', 'simple-events-by-mime' ) ); ?>

			<div class="wpse-event-fields-grid">
				<?php $this->render_input( 'event-url', 'event_url', __( 'External event URL', 'simple-events-by-mime' ), 'url', $input->event_url, null, 2048, __( 'Optional information or registration page using HTTP(S).', 'simple-events-by-mime' ) ); ?>
				<?php $this->render_input( 'event-url-label', 'event_url_label', __( 'External event link label', 'simple-events-by-mime' ), 'text', $input->event_url_label, null, EventMetaSanitizer::EVENT_URL_LABEL_MAX_LENGTH, __( 'Optional link text. The default is “More event information”.', 'simple-events-by-mime' ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Load assets only for the event post editor.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || EventPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$base_url = plugin_dir_url( WPSE_PLUGIN_FILE );

		wp_enqueue_style(
			'wpse-event-editor',
			$base_url . 'assets/src/css/admin-event.css',
			array(),
			WPSE_VERSION
		);
		wp_enqueue_script(
			'wpse-event-editor',
			$base_url . 'assets/src/js/admin-event.js',
			array( 'wp-data' ),
			WPSE_VERSION,
			true
		);
	}

	/**
	 * Render one labeled input.
	 *
	 * @param string      $id          Field ID suffix.
	 * @param string      $name        Payload field name.
	 * @param string      $label       Translated field label.
	 * @param string      $type        HTML input type.
	 * @param string      $value       Stored value.
	 * @param string|null $step        Optional time step.
	 * @param int|null    $max_length  Optional maximum length.
	 * @param string      $description Optional translated description.
	 */
	private function render_input(
		string $id,
		string $name,
		string $label,
		string $type,
		string $value,
		?string $step = null,
		?int $max_length = null,
		string $description = ''
	): void {
		$field_id = 'wpse-' . $id;
		?>
		<p class="wpse-event-fields-field">
			<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input
				class="widefat"
				type="<?php echo esc_attr( $type ); ?>"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="wpse_event[<?php echo esc_attr( $name ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				<?php
				if ( null !== $step ) :
					?>
					step="<?php echo esc_attr( $step ); ?>"<?php endif; ?>
				<?php
				if ( null !== $max_length ) :
					?>
					maxlength="<?php echo esc_attr( (string) $max_length ); ?>"<?php endif; ?>
			>
			<?php if ( '' !== $description ) : ?>
				<span class="description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Render the address field.
	 *
	 * @param string $id         Field ID suffix.
	 * @param string $name       Payload field name.
	 * @param string $label      Translated label.
	 * @param string $value      Stored value.
	 * @param int    $max_length Maximum length.
	 */
	private function render_textarea( string $id, string $name, string $label, string $value, int $max_length ): void {
		$field_id = 'wpse-' . $id;
		?>
		<p class="wpse-event-fields-field">
			<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<textarea class="widefat" rows="3" id="<?php echo esc_attr( $field_id ); ?>" name="wpse_event[<?php echo esc_attr( $name ); ?>]" maxlength="<?php echo esc_attr( (string) $max_length ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Render explicit event status options.
	 *
	 * @param string $current_status Stored status.
	 */
	private function render_status( string $current_status ): void {
		$options = array(
			EventStatus::SCHEDULED->value => __( 'Scheduled', 'simple-events-by-mime' ),
			EventStatus::CANCELLED->value => __( 'Cancelled', 'simple-events-by-mime' ),
			EventStatus::POSTPONED->value => __( 'Postponed', 'simple-events-by-mime' ),
		);
		?>
		<p class="wpse-event-fields-field">
			<label for="wpse-status"><?php esc_html_e( 'Event status', 'simple-events-by-mime' ); ?></label>
			<select class="widefat" id="wpse-status" name="wpse_event[status]">
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Build editor input from stored canonical metadata.
	 *
	 * @param int $post_id Event post ID.
	 */
	private function stored_input( int $post_id ): EventInput {
		$timezone = $this->stored_string( $post_id, EventMeta::TIMEZONE );
		$status   = $this->stored_string( $post_id, EventMeta::STATUS );
		$all_day  = get_post_meta( $post_id, EventMeta::ALL_DAY, true );

		return EventInput::from_canonical(
			$this->stored_string( $post_id, EventMeta::START_LOCAL ),
			$this->stored_string( $post_id, EventMeta::END_LOCAL ),
			( is_bool( $all_day ) || is_string( $all_day ) || is_int( $all_day ) )
				&& rest_sanitize_boolean( $all_day ),
			'' !== $timezone ? $timezone : wp_timezone_string(),
			$this->stored_string( $post_id, EventMeta::VENUE ),
			$this->stored_string( $post_id, EventMeta::ADDRESS ),
			$this->stored_string( $post_id, EventMeta::LOCATION_URL ),
			$this->stored_string( $post_id, EventMeta::EVENT_URL ),
			$this->stored_string( $post_id, EventMeta::EVENT_URL_LABEL ),
			'' !== $status ? $status : EventStatus::SCHEDULED->value
		);
	}

	/**
	 * Read scalar stored metadata.
	 *
	 * @param int    $post_id  Event ID.
	 * @param string $meta_key Registered meta key.
	 */
	private function stored_string( int $post_id, string $meta_key ): string {
		$value = get_post_meta( $post_id, $meta_key, true );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Determine whether copied dates still require editor review.
	 *
	 * @param int $post_id Event post ID.
	 */
	private function dates_need_review( int $post_id ): bool {
		$value = get_post_meta( $post_id, EventMeta::DATES_NEED_REVIEW, true );

		return true === $value || 1 === $value || '1' === $value;
	}
}
