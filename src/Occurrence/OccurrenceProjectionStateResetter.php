<?php
/**
 * WordPress adapter for disposable event projection markers.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Content\EventMeta;

/**
 * Makes every canonical event eligible for bounded re-projection.
 */
final class OccurrenceProjectionStateResetter implements OccurrenceProjectionStateLifecycle {
	/** Remove only plugin-owned derived projection markers. */
	public function reset(): void {
		delete_post_meta_by_key( EventMeta::ACTIVE_GENERATION );
		delete_post_meta_by_key( EventMeta::INDEX_DIRTY );
	}
}
