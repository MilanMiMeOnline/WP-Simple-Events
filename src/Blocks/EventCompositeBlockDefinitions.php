<?php
/**
 * Stable primary Gutenberg event-component catalogue.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

/** Keeps composite registration and rendering on one three-block allowlist. */
final class EventCompositeBlockDefinitions {
	/**
	 * Component keys indexed by stable block slug.
	 *
	 * @var array<string, string>
	 */
	private const BLOCKS = array(
		'event-list'     => 'list',
		'event-calendar' => 'calendar',
		'event-details'  => 'details',
	);

	/**
	 * Return stable block slugs in inserter order.
	 *
	 * @return list<string>
	 */
	public static function slugs(): array {
		return array_keys( self::BLOCKS );
	}

	/**
	 * Return the allowlisted component key for a complete block name.
	 *
	 * @param string $block_name Registered block name.
	 */
	public static function component( string $block_name ): ?string {
		if ( ! str_starts_with( $block_name, 'wpse/' ) ) {
			return null;
		}

		$slug = substr( $block_name, 5 );

		return self::BLOCKS[ $slug ] ?? null;
	}
}
