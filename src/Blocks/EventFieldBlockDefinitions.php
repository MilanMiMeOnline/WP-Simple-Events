<?php
/**
 * Stable atomic Gutenberg block catalogue.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

/** Keeps registration, rendering and patterns on one allowlisted field palette. */
final class EventFieldBlockDefinitions {
	public const CATEGORY = 'wp-simple-events';

	/**
	 * Human-readable field labels keyed by block slug.
	 *
	 * @var array<string, string>
	 */
	private const BLOCKS = array(
		'event-title'           => 'title',
		'event-featured-image'  => 'featured image',
		'event-date-time'       => 'date and time',
		'event-status'          => 'status',
		'event-venue'           => 'venue',
		'event-address'         => 'address',
		'event-location-link'   => 'location link',
		'event-content'         => 'content',
		'event-excerpt'         => 'excerpt',
		'event-external-action' => 'external action',
		'event-categories'      => 'categories',
		'event-tags'            => 'tags',
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
	 * Return stable human-readable field labels in inserter order.
	 *
	 * @return list<string>
	 */
	public static function labels(): array {
		return array_values( self::BLOCKS );
	}

	/**
	 * Determine whether a complete registered block name belongs to the palette.
	 *
	 * @param string $block_name Registered block name.
	 */
	public static function contains( string $block_name ): bool {
		return str_starts_with( $block_name, 'wpse/' )
			&& array_key_exists( substr( $block_name, 5 ), self::BLOCKS );
	}

	/**
	 * Return the allowlisted slug for a complete registered name.
	 *
	 * @param string $block_name Registered block name.
	 */
	public static function slug( string $block_name ): ?string {
		return self::contains( $block_name ) ? substr( $block_name, 5 ) : null;
	}
}
