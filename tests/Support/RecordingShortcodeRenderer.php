<?php
/**
 * Recording shortcode renderer test double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

/** Records normalized attributes and returns deterministic native markup. */
final class RecordingShortcodeRenderer implements ShortcodeRenderer {
	/**
	 * Last normalized renderer attributes.
	 *
	 * @var array<string, mixed>
	 */
	public array $attributes = array();

	/**
	 * Create the deterministic renderer.
	 *
	 * @param string $output Renderer output.
	 */
	public function __construct( private readonly string $output ) {}

	/**
	 * Record normalized attributes and return the configured markup.
	 *
	 * @param array<string, mixed>|string $attributes Native render attributes.
	 */
	public function render( array|string $attributes = array() ): string {
		$this->attributes = is_array( $attributes ) ? $attributes : array();

		return $this->output;
	}
}
