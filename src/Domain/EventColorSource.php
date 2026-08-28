<?php
/**
 * Resolved event color sources.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/** Identifies only normalized presentation origins, never stored CSS. */
enum EventColorSource: string {
	case CUSTOM   = 'custom';
	case CATEGORY = 'category';
	case FALLBACK = 'fallback';
}
