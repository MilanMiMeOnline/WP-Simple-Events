<?php
/**
 * Native event details meta box.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Application\EventInput;
use MiMe\WPSimpleEvents\Application\RecurrenceScheduleOwnership;
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
	 * Create the native editor boundary.
	 *
	 * @param RecurrenceScheduleOwnership $schedule_ownership Protected schedule ownership boundary.
	 */
	public function __construct(
		private readonly RecurrenceScheduleOwnership $schedule_ownership = new RecurrenceScheduleOwnership()
	) {}

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
			__( 'Event details', 'mime-simple-events-calendar' ),
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
		$input          = $this->stored_input( $post->ID );
		$schedule_owned = $this->schedule_ownership->owns( $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div
			class="wpse-event-fields"
			data-wpse-event-fields
			data-wpse-schedule-owner="<?php echo $schedule_owned ? 'recurrence' : 'event'; ?>"
		>
			<p class="wpse-event-fields-intro" data-wpse-schedule-intro
				<?php
				if ( $schedule_owned ) :
					?>
					hidden<?php endif; ?>
			>
				<?php esc_html_e( 'A start is required before an event can be published. Drafts may remain incomplete.', 'mime-simple-events-calendar' ); ?>
			</p>

			<div
				class="notice notice-info inline wpse-event-schedule-notice"
				data-wpse-recurrence-schedule-notice
				role="status"
				<?php
				if ( ! $schedule_owned ) :
					?>
					hidden<?php endif; ?>
			>
				<p>
					<strong><?php esc_html_e( 'This event’s schedule is managed in the block editor.', 'mime-simple-events-calendar' ); ?></strong>
					<?php esc_html_e( 'Dates, times, the all-day setting and timezone are managed in the Repeating event panel in the block editor. Choose Complete series, Only this occurrence, or This and following there. The event status below applies to the complete series.', 'mime-simple-events-calendar' ); ?>
				</p>
			</div>

			<?php if ( $this->dates_need_review( $post->ID ) ) : ?>
				<div class="notice notice-warning inline wpse-event-date-review" role="status">
					<p><strong><?php esc_html_e( 'Review the copied start and end date before publishing this event.', 'mime-simple-events-calendar' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<div data-wpse-schedule-fields
				<?php
				if ( $schedule_owned ) :
					?>
					hidden<?php endif; ?>
			>
				<p class="wpse-event-fields-all-day">
					<label for="wpse-all-day">
						<input type="checkbox" id="wpse-all-day" name="wpse_event[all_day]" value="1" <?php checked( $input->all_day ); ?>
							<?php
							if ( $schedule_owned ) :
								?>
								disabled<?php endif; ?>
						>
						<?php esc_html_e( 'All-day event', 'mime-simple-events-calendar' ); ?>
					</label>
				</p>

				<div class="wpse-event-fields-grid">
					<?php $this->render_input( 'start-date', 'start_date', __( 'Start date', 'mime-simple-events-calendar' ), 'date', $input->start_date, null, null, '', $schedule_owned ); ?>
					<div data-wpse-time-field>
						<?php $this->render_input( 'start-time', 'start_time', __( 'Start time', 'mime-simple-events-calendar' ), 'time', $input->start_time, '60', null, '', $schedule_owned ); ?>
					</div>
					<?php $this->render_input( 'end-date', 'end_date', __( 'End date', 'mime-simple-events-calendar' ), 'date', $input->end_date, null, null, '', $schedule_owned ); ?>
					<div data-wpse-time-field>
						<?php $this->render_input( 'end-time', 'end_time', __( 'End time', 'mime-simple-events-calendar' ), 'time', $input->end_time, '60', null, '', $schedule_owned ); ?>
					</div>
				</div>

				<p class="description wpse-event-fields-timezone">
					<?php
					printf(
						/* translators: %s: Event timezone identifier. */
						esc_html__( 'Timezone: %s. Existing events keep their saved timezone.', 'mime-simple-events-calendar' ),
						esc_html( $input->timezone )
					);
					?>
				</p>
				<p class="description wpse-event-fields-time-format">
					<?php esc_html_e( 'Time controls may look different across browsers. Events are saved with the same canonical 24-hour value; public output follows the WordPress time format.', 'mime-simple-events-calendar' ); ?>
				</p>
			</div>

			<?php if ( $schedule_owned ) : ?>
				<div data-wpse-schedule-shadow hidden>
					<input type="hidden" name="wpse_event[all_day]" value="<?php echo $input->all_day ? '1' : '0'; ?>">
					<input type="hidden" name="wpse_event[start_date]" value="<?php echo esc_attr( $input->start_date ); ?>">
					<input type="hidden" name="wpse_event[start_time]" value="<?php echo esc_attr( $input->start_time ); ?>">
					<input type="hidden" name="wpse_event[end_date]" value="<?php echo esc_attr( $input->end_date ); ?>">
					<input type="hidden" name="wpse_event[end_time]" value="<?php echo esc_attr( $input->end_time ); ?>">
				</div>
			<?php endif; ?>

			<div class="wpse-event-fields-grid">
				<?php $this->render_input( 'venue', 'venue', __( 'Venue', 'mime-simple-events-calendar' ), 'text', $input->venue, null, 200 ); ?>
				<?php $this->render_status( $input->status ); ?>
			</div>

			<?php $this->render_textarea( 'address', 'address', __( 'Address', 'mime-simple-events-calendar' ), $input->address, 500 ); ?>

			<?php $this->render_input( 'location-url', 'location_url', __( 'Location URL', 'mime-simple-events-calendar' ), 'url', $input->location_url, null, 2048, __( 'Optional route or location page using HTTP(S).', 'mime-simple-events-calendar' ) ); ?>

			<div class="wpse-event-fields-grid">
				<?php $this->render_input( 'event-url', 'event_url', __( 'External event URL', 'mime-simple-events-calendar' ), 'url', $input->event_url, null, 2048, __( 'Optional information or registration page using HTTP(S).', 'mime-simple-events-calendar' ) ); ?>
				<?php $this->render_input( 'event-url-label', 'event_url_label', __( 'External event link label', 'mime-simple-events-calendar' ), 'text', $input->event_url_label, null, EventMetaSanitizer::EVENT_URL_LABEL_MAX_LENGTH, __( 'Optional link text. The default is “More event information”.', 'mime-simple-events-calendar' ) ); ?>
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
	 * @param bool        $disabled    Whether recurrence owns this control.
	 */
	private function render_input(
		string $id,
		string $name,
		string $label,
		string $type,
		string $value,
		?string $step = null,
		?int $max_length = null,
		string $description = '',
		bool $disabled = false
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
				<?php
				if ( $disabled ) :
					?>
					disabled<?php endif; ?>
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
			EventStatus::SCHEDULED->value => __( 'Scheduled', 'mime-simple-events-calendar' ),
			EventStatus::CANCELLED->value => __( 'Cancelled', 'mime-simple-events-calendar' ),
			EventStatus::POSTPONED->value => __( 'Postponed', 'mime-simple-events-calendar' ),
		);
		?>
		<p class="wpse-event-fields-field">
			<label for="wpse-status"><?php esc_html_e( 'Event status', 'mime-simple-events-calendar' ); ?></label>
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
