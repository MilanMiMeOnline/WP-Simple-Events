<?php
/**
 * Occurrence read failure.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use RuntimeException;

/**
 * Signals that the projection read path must fail closed and use its safe fallback.
 */
final class OccurrenceReadException extends RuntimeException {}
