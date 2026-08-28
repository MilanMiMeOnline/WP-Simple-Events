<?php
/**
 * Native Add to Calendar display preference.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/** Resolves the explicit global opt-in for plugin-owned single templates. */
final class NativeCalendarActionSettings {
	public const OPTION = 'wpse_show_native_calendar_action';

	/** Determine whether native event pages should append the action. */
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
