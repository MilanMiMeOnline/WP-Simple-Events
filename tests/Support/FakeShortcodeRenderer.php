<?php
/**
 * Shortcode renderer spy for Elementor adapter tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

/**
 * Captures attributes and returns configured trusted renderer output.
 */
final class FakeShortcodeRenderer implements ShortcodeRenderer {
	/**
	 * Last captured attributes.
	 *
	 * @var array<string, mixed>|string|null
	 */
	public array|string|null $attributes = null;

	/**
	 * Create configured renderer output.
	 *
	 * @param string $output Trusted renderer output.
	 */
	public function __construct( private readonly string $output = '<div class="rendered">Event output</div>' ) {}

	/**
	 * Capture attributes and return configured output.
	 *
	 * @param array<string, mixed>|string $attributes Render attributes.
	 */
	public function render( array|string $attributes = array() ): string {
		$this->attributes = $attributes;

		return $this->output;
	}
}
