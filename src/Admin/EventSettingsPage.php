<?php
/**
 * Native event settings page.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Frontend\EventTimezoneDisplaySettings;
use MiMe\WPSimpleEvents\Lifecycle\UninstallSettings;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Routing\EventArchiveSlugConflictDetector;
use MiMe\WPSimpleEvents\Seo\StructuredDataSettings;
use WP_Screen;

/**
 * Registers the small event settings screen through the WordPress Settings API.
 */
final class EventSettingsPage {
	private const PAGE_SLUG          = 'wpse-settings';
	private const SETTINGS_GROUP     = 'wpse_settings';
	private const ARCHIVE_SECTION    = 'wpse_archive_settings';
	private const DISPLAY_SECTION    = 'wpse_display_settings';
	private const ADVANCED_SECTION   = 'wpse_advanced_settings';
	private const MAINTENANCE_STATES = array(
		'capabilities_repaired',
		'reindex_progress',
		'reindex_complete',
	);

	/**
	 * Create the settings screen.
	 *
	 * @param EventArchiveSettings             $archive_settings Validated archive settings.
	 * @param EventArchiveSlugConflictDetector $slug_conflicts   Page conflict detector.
	 * @param EventTimezoneDisplaySettings     $timezone_display Global timezone-display setting.
	 */
	public function __construct(
		private readonly EventArchiveSettings $archive_settings = new EventArchiveSettings(),
		private readonly EventArchiveSlugConflictDetector $slug_conflicts = new EventArchiveSlugConflictDetector(),
		private readonly EventTimezoneDisplaySettings $timezone_display = new EventTimezoneDisplaySettings()
	) {}

	/**
	 * Return the canonical local settings URL.
	 */
	public static function url(): string {
		return admin_url( 'edit.php?post_type=' . EventPostType::POST_TYPE . '&page=' . self::PAGE_SLUG );
	}

	/**
	 * Register admin lifecycle callbacks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'render_archive_conflict_notice' ) );
	}

	/**
	 * Add Settings below the native Events menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . EventPostType::POST_TYPE,
			__( 'Event settings', 'wp-simple-events' ),
			__( 'Settings', 'wp-simple-events' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the currently implemented display and ownership settings.
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			EventArchiveSettings::SLUG_OPTION,
			array(
				'type'              => 'string',
				'default'           => EventArchiveSettings::DEFAULT_SLUG,
				'sanitize_callback' => array( $this->archive_settings, 'sanitize_slug' ),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			EventArchiveSettings::PER_PAGE_OPTION,
			array(
				'type'              => 'integer',
				'default'           => $this->archive_settings->per_page(),
				'sanitize_callback' => array( $this->archive_settings, 'sanitize_per_page' ),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			EventArchiveSettings::DEFAULT_PERIOD_OPTION,
			array(
				'type'              => 'string',
				'default'           => EventPeriod::UPCOMING->value,
				'sanitize_callback' => array( $this->archive_settings, 'sanitize_default_period' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			self::ARCHIVE_SECTION,
			__( 'Event archive', 'wp-simple-events' ),
			array( $this, 'render_archive_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			EventArchiveSettings::SLUG_OPTION,
			__( 'Archive slug', 'wp-simple-events' ),
			array( $this, 'render_archive_slug_field' ),
			self::PAGE_SLUG,
			self::ARCHIVE_SECTION,
			array( 'label_for' => EventArchiveSettings::SLUG_OPTION )
		);

		add_settings_field(
			EventArchiveSettings::PER_PAGE_OPTION,
			__( 'Events per page', 'wp-simple-events' ),
			array( $this, 'render_archive_per_page_field' ),
			self::PAGE_SLUG,
			self::ARCHIVE_SECTION,
			array( 'label_for' => EventArchiveSettings::PER_PAGE_OPTION )
		);

		add_settings_field(
			EventArchiveSettings::DEFAULT_PERIOD_OPTION,
			__( 'Default period', 'wp-simple-events' ),
			array( $this, 'render_archive_period_field' ),
			self::PAGE_SLUG,
			self::ARCHIVE_SECTION,
			array( 'label_for' => EventArchiveSettings::DEFAULT_PERIOD_OPTION )
		);

		register_setting(
			self::SETTINGS_GROUP,
			StructuredDataSettings::OPTION,
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			self::DISPLAY_SECTION,
			__( 'Display', 'wp-simple-events' ),
			array( $this, 'render_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'wpse_site_timezone',
			__( 'Site timezone', 'wp-simple-events' ),
			array( $this, 'render_site_timezone_field' ),
			self::PAGE_SLUG,
			self::DISPLAY_SECTION
		);

		register_setting(
			self::SETTINGS_GROUP,
			EventTimezoneDisplaySettings::OPTION,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( $this->timezone_display, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_field(
			EventTimezoneDisplaySettings::OPTION,
			__( 'Public event timezone', 'wp-simple-events' ),
			array( $this, 'render_timezone_display_field' ),
			self::PAGE_SLUG,
			self::DISPLAY_SECTION,
			array( 'label_for' => EventTimezoneDisplaySettings::OPTION )
		);

		add_settings_field(
			StructuredDataSettings::OPTION,
			__( 'Event structured data', 'wp-simple-events' ),
			array( $this, 'render_structured_data_field' ),
			self::PAGE_SLUG,
			self::DISPLAY_SECTION,
			array( 'label_for' => StructuredDataSettings::OPTION )
		);

		register_setting(
			self::SETTINGS_GROUP,
			UninstallSettings::OPTION,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			self::ADVANCED_SECTION,
			__( 'Data ownership', 'wp-simple-events' ),
			array( $this, 'render_data_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			UninstallSettings::OPTION,
			__( 'Delete plugin data', 'wp-simple-events' ),
			array( $this, 'render_uninstall_field' ),
			self::PAGE_SLUG,
			self::ADVANCED_SECTION,
			array( 'label_for' => UninstallSettings::OPTION )
		);
	}

	/**
	 * Accept only the checkbox's explicit enabled representations.
	 *
	 * @param mixed $value Submitted option value.
	 */
	public function sanitize_checkbox( mixed $value ): bool {
		return in_array( $value, array( true, 1, '1' ), true );
	}

	/**
	 * Render the settings page for administrators.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage event settings.', 'wp-simple-events' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
			<?php $this->render_maintenance_tools(); ?>
		</div>
		<?php
	}

	/**
	 * Explain the native archive settings and visitor filters.
	 */
	public function render_archive_section(): void {
		echo '<p>'
			. esc_html__( 'Configure the native event archive. Visitors can still switch between upcoming, past and all events with the archive filter.', 'wp-simple-events' )
			. '</p>';
	}

	/**
	 * Render the single-segment archive slug field.
	 */
	public function render_archive_slug_field(): void {
		$name = esc_attr( EventArchiveSettings::SLUG_OPTION );
		?>
		<input id="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" type="text" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" value="<?php echo esc_attr( $this->archive_settings->slug() ); ?>" maxlength="<?php echo esc_attr( (string) EventArchiveSettings::MAX_SLUG_LENGTH ); ?>" class="regular-text" autocomplete="off">
		<p class="description"><?php esc_html_e( 'One URL segment without slashes. This also changes individual event URLs; existing links are not redirected. Saving a changed slug rebuilds WordPress permalink rules once.', 'wp-simple-events' ); ?></p>
		<?php
	}

	/**
	 * Render the bounded archive page-size field.
	 */
	public function render_archive_per_page_field(): void {
		$name = esc_attr( EventArchiveSettings::PER_PAGE_OPTION );
		?>
		<input id="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" type="number" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" value="<?php echo esc_attr( (string) $this->archive_settings->per_page() ); ?>" min="1" max="50" step="1" class="small-text">
		<p class="description"><?php esc_html_e( 'Between 1 and 50 public events per archive page.', 'wp-simple-events' ); ?></p>
		<?php
	}

	/**
	 * Render the allowlisted default archive-period field.
	 */
	public function render_archive_period_field(): void {
		$name   = esc_attr( EventArchiveSettings::DEFAULT_PERIOD_OPTION );
		$period = $this->archive_settings->default_period();
		?>
		<select id="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>">
			<option value="upcoming" <?php selected( $period->value, EventPeriod::UPCOMING->value ); ?>><?php esc_html_e( 'Upcoming and active events', 'wp-simple-events' ); ?></option>
			<option value="all" <?php selected( $period->value, EventPeriod::ALL->value ); ?>><?php esc_html_e( 'All events, including past events', 'wp-simple-events' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Warn when a WordPress page and the event archive claim the same path.
	 */
	public function render_archive_conflict_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof WP_Screen || EventPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$slug = $this->archive_settings->slug();

		if ( ! $this->slug_conflicts->has_page_conflict( $slug ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: configured event archive path. */
			__( 'The event archive and an existing WordPress page both use /%s/. Keep this slug to use the native event archive at that address, or change it below so the page can use the address.', 'wp-simple-events' ),
			$slug
		);

		echo '<div class="notice notice-warning inline"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Explain the intentionally small display-settings section.
	 */
	public function render_section(): void {
		echo '<p>'
			. esc_html__( 'Control plugin output that may overlap with your theme or SEO plugin.', 'wp-simple-events' )
			. '</p>';
	}

	/**
	 * Report the authoritative WordPress timezone without duplicating its control.
	 */
	public function render_site_timezone_field(): void {
		$timezone     = wp_timezone_string();
		$fixed_offset = 1 === preg_match( '/^[+-]\d{2}:\d{2}$/D', $timezone );
		?>
		<p><code><?php echo esc_html( $timezone ); ?></code></p>
		<p class="description"><?php esc_html_e( 'New events capture this timezone. Existing events keep the timezone saved with them when the WordPress setting changes.', 'wp-simple-events' ); ?></p>
		<?php if ( $fixed_offset ) : ?>
			<p class="description"><?php esc_html_e( 'This fixed UTC offset does not adjust for daylight saving time.', 'wp-simple-events' ); ?></p>
		<?php endif; ?>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<p><a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'Change the site timezone in WordPress General Settings', 'wp-simple-events' ); ?></a></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the backward-compatible public timezone visibility toggle.
	 */
	public function render_timezone_display_field(): void {
		$enabled = $this->timezone_display->enabled();
		$name    = esc_attr( EventTimezoneDisplaySettings::OPTION );
		?>
		<input type="hidden" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" value="0">
		<label>
			<input id="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" type="checkbox" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" value="1" <?php echo checked( $enabled, true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core checked() returns a fixed HTML attribute. ?>>
			<?php esc_html_e( 'Show the captured timezone and applicable UTC offset with timed event details.', 'wp-simple-events' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'All-day events omit timezone information. This setting changes presentation only, never saved event dates or times.', 'wp-simple-events' ); ?></p>
		<?php
	}

	/**
	 * Explain that event content belongs to the site by default.
	 */
	public function render_data_section(): void {
		echo '<p>'
			. esc_html__( 'Event content is preserved when the plugin is deactivated or deleted unless you explicitly choose otherwise below.', 'wp-simple-events' )
			. '</p>';
	}

	/**
	 * Render the structured-data checkbox and explicit unchecked value.
	 */
	public function render_structured_data_field(): void {
		$option  = get_option( StructuredDataSettings::OPTION, true );
		$enabled = true === $option || 1 === $option || '1' === $option;
		$name    = esc_attr( StructuredDataSettings::OPTION );
		?>
		<input type="hidden" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" value="0">
		<label>
			<input id="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" type="checkbox" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" value="1" <?php echo checked( $enabled, true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core checked() returns a fixed HTML attribute. ?>>
			<?php esc_html_e( 'Output JSON-LD on individual public event pages.', 'wp-simple-events' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Disable this when another SEO plugin already outputs Event structured data.', 'wp-simple-events' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the explicit destructive uninstall opt-in.
	 */
	public function render_uninstall_field(): void {
		$enabled = ( new UninstallSettings() )->delete_data();
		$name    = esc_attr( UninstallSettings::OPTION );
		?>
		<input type="hidden" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" value="0">
		<label>
			<input id="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" type="checkbox" name="<?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped immediately above. ?>" value="1" <?php echo checked( $enabled, true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core checked() returns a fixed HTML attribute. ?>>
			<?php esc_html_e( 'Permanently delete events, event categories, event tags, plugin settings and event capabilities when the plugin is deleted.', 'wp-simple-events' ); ?>
		</label>
		<p class="description">
			<strong><?php esc_html_e( 'This cannot be undone.', 'wp-simple-events' ); ?></strong>
			<?php esc_html_e( ' Uploaded media is retained because it may be used elsewhere. Deactivation never deletes data.', 'wp-simple-events' ); ?>
		</p>
		<?php
	}

	/**
	 * Render separate nonce-protected maintenance forms outside the settings form.
	 */
	private function render_maintenance_tools(): void {
		$state = $this->maintenance_state();

		$this->render_maintenance_notice( $state );
		?>
		<hr>
		<h2><?php esc_html_e( 'Maintenance', 'wp-simple-events' ); ?></h2>
		<p><?php esc_html_e( 'Use these tools only to repair existing event data. They do not change event content or presentation settings.', 'wp-simple-events' ); ?></p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( EventMaintenanceController::REPAIR_CAPABILITIES_ACTION ); ?>">
			<?php wp_nonce_field( EventMaintenanceController::REPAIR_CAPABILITIES_ACTION ); ?>
			<?php submit_button( __( 'Repair event capabilities', 'wp-simple-events' ), 'secondary', 'submit', false ); ?>
		</form>
		<p class="description"><?php esc_html_e( 'Restores the documented event permissions for administrators and editors without changing other role permissions.', 'wp-simple-events' ); ?></p>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( EventMaintenanceController::REINDEX_ACTION ); ?>">
			<input type="hidden" name="wpse_page" value="<?php echo esc_attr( (string) $state['page'] ); ?>">
			<input type="hidden" name="wpse_processed" value="<?php echo esc_attr( (string) $state['processed'] ); ?>">
			<input type="hidden" name="wpse_changed" value="<?php echo esc_attr( (string) $state['changed'] ); ?>">
			<input type="hidden" name="wpse_skipped" value="<?php echo esc_attr( (string) $state['skipped'] ); ?>">
			<input type="hidden" name="wpse_failed" value="<?php echo esc_attr( (string) $state['failed'] ); ?>">
			<?php wp_nonce_field( EventMaintenanceController::REINDEX_ACTION ); ?>
			<?php
			submit_button(
				'reindex_progress' === $state['status']
					? __( 'Continue rebuilding date indexes', 'wp-simple-events' )
					: __( 'Rebuild event date indexes', 'wp-simple-events' ),
				'secondary',
				'submit',
				false
			);
			?>
		</form>
		<p class="description"><?php esc_html_e( 'Recalculates only derived UTC indexes from validated local event dates, in batches of 50. Invalid events are skipped for manual review.', 'wp-simple-events' ); ?></p>
		<?php
	}

	/**
	 * Parse allowlisted read-only maintenance feedback and bounded counters.
	 *
	 * @return array{status: string, page: int, processed: int, changed: int, skipped: int, failed: int}
	 */
	private function maintenance_state(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only feedback after protected admin-post redirects; values are allowlisted and bounded.
		$status = isset( $_GET['wpse_maintenance'] ) && is_string( $_GET['wpse_maintenance'] )
			? sanitize_text_field( wp_unslash( $_GET['wpse_maintenance'] ) )
			: '';
		$status = in_array( $status, self::MAINTENANCE_STATES, true ) ? $status : '';

		return array(
			'status'    => $status,
			'page'      => $this->query_counter( 'wpse_page', 1, 1_000_000 ),
			'processed' => $this->query_counter( 'wpse_processed', 0, 1_000_000_000 ),
			'changed'   => $this->query_counter( 'wpse_changed', 0, 1_000_000_000 ),
			'skipped'   => $this->query_counter( 'wpse_skipped', 0, 1_000_000_000 ),
			'failed'    => $this->query_counter( 'wpse_failed', 0, 1_000_000_000 ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Read one bounded display-only query counter.
	 *
	 * @param string $key      Query key.
	 * @param int    $fallback Fallback value.
	 * @param int    $maximum  Inclusive maximum.
	 */
	private function query_counter( string $key, int $fallback, int $maximum ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only continuation value; mutation requires a separate verified POST nonce.
		$value = $_GET[ $key ] ?? null;

		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$value = sanitize_text_field( wp_unslash( $value ) );

		return '' !== $value && ctype_digit( $value )
			? max( $fallback, min( $maximum, (int) $value ) )
			: $fallback;
	}

	/**
	 * Render maintenance feedback with no event titles or personal data.
	 *
	 * @param array{status: string, page: int, processed: int, changed: int, skipped: int, failed: int} $state Maintenance state.
	 */
	private function render_maintenance_notice( array $state ): void {
		if ( 'capabilities_repaired' === $state['status'] ) {
			echo '<div class="notice notice-success inline"><p>'
				. esc_html__( 'Event capabilities were restored for administrators and editors.', 'wp-simple-events' )
				. '</p></div>';
			return;
		}

		if ( ! in_array( $state['status'], array( 'reindex_progress', 'reindex_complete' ), true ) ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: processed events, 2: changed indexes, 3: invalid skipped events, 4: failed writes. */
			__( 'Date index maintenance inspected %1$d events: %2$d changed, %3$d skipped as invalid and %4$d write failures.', 'wp-simple-events' ),
			$state['processed'],
			$state['changed'],
			$state['skipped'],
			$state['failed']
		);
		$class = 'reindex_complete' === $state['status'] && 0 === $state['failed']
			? 'notice notice-success inline'
			: 'notice notice-warning inline';

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}
}
