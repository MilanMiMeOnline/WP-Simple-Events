<?php
/**
 * Structured-data output settings.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

/**
 * Resolves the global setting and stable per-event override filter.
 */
final class StructuredDataSettings {
	public const OPTION = 'wpse_structured_data_enabled';

	/**
	 * Determine whether the plugin may output schema for one event.
	 *
	 * @param int $event_id Public event post ID.
	 */
	public function enabled( int $event_id ): bool {
		$option  = get_option( self::OPTION, true );
		$enabled = true === $option || 1 === $option || '1' === $option;

		/**
		 * Filters whether Simple Events by MiMe outputs JSON-LD for an event.
		 *
		 * Return false when another SEO integration owns Event schema output.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $enabled  Current global setting.
		 * @param int  $event_id Event post ID.
		 */
		return (bool) apply_filters( 'wpse_structured_data_enabled', $enabled, $event_id );
	}
}
