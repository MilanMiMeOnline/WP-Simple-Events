<?php
/**
 * Event date-index repair outcomes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Maintenance;

/**
 * Describes one event's bounded maintenance outcome.
 */
enum EventDateIndexRepairStatus: string {
	case REPAIRED  = 'repaired';
	case CLEARED   = 'cleared';
	case UNCHANGED = 'unchanged';
	case INVALID   = 'invalid';
	case FAILED    = 'failed';
}
