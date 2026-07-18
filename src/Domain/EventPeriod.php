<?php
/**
 * Public event period selection.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/**
 * Defines the bounded period modes supported by public event queries.
 */
enum EventPeriod: string {
	case UPCOMING = 'upcoming';
	case PAST     = 'past';
	case ALL      = 'all';
}
