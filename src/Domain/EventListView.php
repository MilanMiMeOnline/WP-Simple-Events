<?php
/**
 * Event collection view mode.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/**
 * Defines the native collection layouts supported by public renderers.
 */
enum EventListView: string {
	case LIST = 'list';
	case GRID = 'grid';
}
