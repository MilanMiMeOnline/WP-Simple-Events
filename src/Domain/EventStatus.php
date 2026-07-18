<?php
/**
 * Event status values.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/**
 * Public event status, separate from the WordPress post status.
 */
enum EventStatus: string {
	case SCHEDULED = 'scheduled';
	case CANCELLED = 'cancelled';
	case POSTPONED = 'postponed';

	/**
	 * Return all allowed stored values.
	 *
	 * @return list<string>
	 */
	public static function values(): array {
		return array_column( self::cases(), 'value' );
	}
}
