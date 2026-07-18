<?php
/**
 * Validated event data.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Immutable event values safe to persist.
 */
final readonly class ValidatedEventData {
	/**
	 * Store validated event fields.
	 *
	 * @param EventDateRange|null $date_range   Validated range, or null for an incomplete draft.
	 * @param bool                $all_day      All-day selection.
	 * @param string              $timezone     Validated timezone.
	 * @param string              $venue        Sanitized venue.
	 * @param string              $address      Sanitized address.
	 * @param string              $location_url Sanitized location URL.
	 * @param string              $event_url    Sanitized event URL.
	 * @param EventStatus         $status       Validated explicit event status.
	 */
	public function __construct(
		public ?EventDateRange $date_range,
		public bool $all_day,
		public string $timezone,
		public string $venue,
		public string $address,
		public string $location_url,
		public string $event_url,
		public EventStatus $status
	) {}
}
