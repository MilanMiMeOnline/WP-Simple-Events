<?php
/**
 * Occurrence index repair boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Repairs one event projection from canonical state only.
 */
interface OccurrenceIndexRepairService {
	/**
	 * Repair one event using its canonical publication status.
	 *
	 * @param int    $event_id    Canonical event post ID.
	 * @param string $post_status WordPress publication status.
	 */
	public function repair( int $event_id, string $post_status ): OccurrenceIndexRepairStatus;
}
