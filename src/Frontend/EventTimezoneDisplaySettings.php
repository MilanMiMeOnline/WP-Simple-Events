<?php
/**
 * Public event-timezone display preference.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Resolves and sanitizes the backward-compatible global display toggle.
 */
final class EventTimezoneDisplaySettings {
	public const OPTION = 'wpse_show_event_timezone';

	/**
	 * Determine whether timed event details should show their captured timezone.
	 */
	public function enabled(): bool {
		return $this->sanitize( get_option( self::OPTION, false ) );
	}

	/**
	 * Accept only the checkbox's explicit enabled representations.
	 *
	 * @param mixed $value Submitted option value.
	 */
	public function sanitize( mixed $value ): bool {
		return in_array( $value, array( true, 1, '1' ), true );
	}
}
