<?php
/**
 * Event structured-data input.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Carries already validated public values into the pure schema builder.
 */
final readonly class EventSchemaInput {
	/**
	 * Store one event schema input.
	 *
	 * @param string      $name        Public event name.
	 * @param string      $start_date  ISO local start date or date-time.
	 * @param string      $end_date    ISO local end date or date-time.
	 * @param EventStatus $status      Public event status.
	 * @param string      $url         Canonical public event URL.
	 * @param string      $description Optional public summary.
	 * @param string      $image_url   Optional public featured image URL.
	 * @param string      $venue       Optional venue name.
	 * @param string      $address     Optional readable address.
	 */
	public function __construct(
		public string $name,
		public string $start_date,
		public string $end_date,
		public EventStatus $status,
		public string $url,
		public string $description = '',
		public string $image_url = '',
		public string $venue = '',
		public string $address = ''
	) {}
}
