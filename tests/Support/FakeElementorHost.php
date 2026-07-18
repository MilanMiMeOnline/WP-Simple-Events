<?php
/**
 * Optional Elementor host double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Elementor\ElementorHost;

/**
 * Supplies deterministic host state to integration tests.
 */
final readonly class FakeElementorHost implements ElementorHost {
	/**
	 * Create deterministic host state.
	 *
	 * @param bool        $loaded       Loaded-hook state.
	 * @param string|null $host_version Detected version.
	 */
	public function __construct( private bool $loaded, private ?string $host_version ) {}

	/** Return configured loaded state. */
	public function is_loaded(): bool {
		return $this->loaded;
	}

	/** Return configured host version. */
	public function version(): ?string {
		return $this->host_version;
	}
}
