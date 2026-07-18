<?php
/**
 * Request-wide rendered component identifiers.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use InvalidArgumentException;

/**
 * Prevents duplicate DOM IDs across independently constructed renderers.
 */
final class RenderInstanceIds {
	public const EVENT_LIST    = 'event-list';
	public const CALENDAR      = 'calendar';
	public const EVENT_DETAILS = 'event-details';

	/**
	 * Counters keyed by component type for the current PHP request.
	 *
	 * @var array<string, int>
	 */
	private static array $counters = array();

	/**
	 * Return the next positive identifier within one component sequence.
	 *
	 * @param string $component Stable internal component key.
	 * @throws InvalidArgumentException When the component key is empty.
	 */
	public static function next( string $component ): int {
		if ( '' === $component ) {
			throw new InvalidArgumentException( 'A rendered component key cannot be empty.' );
		}

		self::$counters[ $component ] = ( self::$counters[ $component ] ?? 0 ) + 1;

		return self::$counters[ $component ];
	}
}
