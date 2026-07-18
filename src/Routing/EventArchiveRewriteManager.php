<?php
/**
 * Event archive rewrite-rule lifecycle.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Routing;

/**
 * Schedules one soft rewrite flush after a real archive slug change.
 */
final class EventArchiveRewriteManager {
	public const PENDING_OPTION = 'wpse_pending_archive_rewrite_slug';

	/**
	 * Create the rewrite manager.
	 *
	 * @param EventArchiveSettings $settings Validated archive settings.
	 */
	public function __construct( private readonly EventArchiveSettings $settings = new EventArchiveSettings() ) {}

	/**
	 * Register option-specific and late-init hooks.
	 */
	public function register(): void {
		add_action( 'update_option_' . EventArchiveSettings::SLUG_OPTION, array( $this, 'updated' ), 10, 3 );
		add_action( 'add_option_' . EventArchiveSettings::SLUG_OPTION, array( $this, 'added' ), 10, 2 );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
	}

	/**
	 * Schedule a flush after an existing slug really changes.
	 *
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $new_value New option value.
	 * @param string $option    Updated option name.
	 */
	public function updated( mixed $old_value, mixed $new_value, string $option = EventArchiveSettings::SLUG_OPTION ): void {
		unset( $option );

		$old_slug = $this->settings->sanitize_slug( $old_value );
		$new_slug = $this->settings->sanitize_slug( $new_value );

		if ( $old_slug !== $new_slug ) {
			update_option( self::PENDING_OPTION, $new_slug, false );
		}
	}

	/**
	 * Schedule a flush when the first stored value differs from the implicit default.
	 *
	 * @param string $option Added option name.
	 * @param mixed  $value  Added option value.
	 */
	public function added( string $option, mixed $value ): void {
		if ( EventArchiveSettings::SLUG_OPTION !== $option ) {
			return;
		}

		$new_slug = $this->settings->sanitize_slug( $value );

		if ( EventArchiveSettings::DEFAULT_SLUG !== $new_slug ) {
			update_option( self::PENDING_OPTION, $new_slug, false );
		}
	}

	/**
	 * Flush once after all post types have registered with the current slug.
	 */
	public function maybe_flush(): void {
		$pending = get_option( self::PENDING_OPTION, null );

		if ( null === $pending ) {
			return;
		}

		if (
			! is_string( $pending )
			|| $pending !== $this->settings->sanitize_slug( $pending )
			|| $pending !== $this->settings->slug()
		) {
			delete_option( self::PENDING_OPTION );
			return;
		}

		flush_rewrite_rules( false );
		delete_option( self::PENDING_OPTION );
	}
}
