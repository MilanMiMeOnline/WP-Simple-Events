<?php
/**
 * Occurrence generation token generator.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Avoids colliding incomplete generations during concurrent editor requests.
 */
final class GenerationTokenGenerator {
	/**
	 * Return a positive 63-bit token supported by signed PHP integers and MySQL.
	 */
	public function generate(): int {
		return random_int( 1, PHP_INT_MAX );
	}
}
