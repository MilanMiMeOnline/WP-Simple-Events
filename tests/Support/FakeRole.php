<?php
/**
 * Minimal WordPress role double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

/**
 * Records capabilities granted by the role manager.
 */
final class FakeRole {
	/**
	 * Granted capabilities.
	 *
	 * @var list<string>
	 */
	private array $capabilities = array();

	/**
	 * Record a granted capability.
	 *
	 * @param string $capability Capability name.
	 */
	public function add_cap( string $capability ): void {
		$this->capabilities[] = $capability;
	}

	/**
	 * Remove a previously granted capability.
	 *
	 * @param string $capability Capability name.
	 */
	public function remove_cap( string $capability ): void {
		$this->capabilities = array_values(
			array_filter(
				$this->capabilities,
				static fn ( string $granted ): bool => $capability !== $granted
			)
		);
	}

	/**
	 * Return granted capabilities.
	 *
	 * @return list<string>
	 */
	public function capabilities(): array {
		return $this->capabilities;
	}
}
