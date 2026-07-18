<?php
/**
 * Destructive uninstall preference.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Lifecycle;

/**
 * Resolves the explicit per-site data-deletion opt-in.
 */
final class UninstallSettings {
	public const OPTION = 'wpse_delete_data_on_uninstall';

	/**
	 * Determine whether the current site explicitly requested full cleanup.
	 */
	public function delete_data(): bool {
		return in_array( get_option( self::OPTION, false ), array( true, 1, '1' ), true );
	}
}
