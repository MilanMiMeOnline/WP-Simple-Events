<?php
/**
 * Safe plugin uninstall orchestration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Lifecycle;

/**
 * Applies the per-site retention preference during plugin deletion.
 */
final class Uninstaller {
	private const SITE_BATCH_SIZE = 100;

	/**
	 * Create the uninstall coordinator.
	 *
	 * @param UninstallSettings $settings Per-site retention preference.
	 * @param SiteDataCleaner   $cleaner  Per-site cleanup service.
	 */
	public function __construct(
		private readonly UninstallSettings $settings = new UninstallSettings(),
		private readonly SiteDataCleaner $cleaner = new SiteDataCleaner()
	) {}

	/**
	 * Process the current site or every site in a multisite network.
	 */
	public function run(): void {
		if ( ! is_multisite() ) {
			$this->clean_current_site_if_enabled();
			return;
		}

		$offset = 0;

		while ( true ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => self::SITE_BATCH_SIZE,
					'offset' => $offset,
				)
			);

			if ( array() === $site_ids ) {
				return;
			}

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );

				try {
					$this->clean_current_site_if_enabled();
				} finally {
					restore_current_blog();
				}
			}

			$count   = count( $site_ids );
			$offset += $count;

			if ( self::SITE_BATCH_SIZE > $count ) {
				return;
			}
		}
	}

	/**
	 * Keep the destructive boundary immediately before cleanup.
	 */
	private function clean_current_site_if_enabled(): void {
		if ( ! $this->settings->delete_data() ) {
			return;
		}

		$this->cleaner->clean();
	}
}
