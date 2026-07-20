<?php
/**
 * Minimal WP_Block runtime double for isolated tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Block' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Exact WordPress runtime double.
	/** Supplies only the block name and inherited context consumed by the plugin. */
	final class WP_Block {
		/**
		 * Parsed block data.
		 *
		 * @var array<string, mixed>
		 */
		public array $parsed_block;

		/**
		 * Inherited block context.
		 *
		 * @var array<string, mixed>
		 */
		public array $context;

		/**
		 * Stable registered block name.
		 *
		 * @var string
		 */
		public string $name;

		/**
		 * Create one deterministic block instance.
		 *
		 * @param array<string, mixed> $parsed_block     Parsed block data.
		 * @param array<string, mixed> $available_context Inherited context values.
		 */
		public function __construct( array $parsed_block, array $available_context = array() ) {
			$this->parsed_block = $parsed_block;
			$this->context      = $available_context;
			$this->name         = is_string( $parsed_block['blockName'] ?? null ) ? $parsed_block['blockName'] : '';
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
