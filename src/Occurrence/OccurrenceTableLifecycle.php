<?php
/**
 * Occurrence table lifecycle boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Isolates lifecycle orchestration from the WordPress database adapter.
 */
interface OccurrenceTableLifecycle {
	/**
	 * Determine whether the current site's projection table exists.
	 */
	public function exists(): bool;

	/**
	 * Create or upgrade the current site's projection table.
	 */
	public function install(): bool;

	/**
	 * Remove the current site's projection table after explicit cleanup opt-in.
	 */
	public function drop(): bool;
}
