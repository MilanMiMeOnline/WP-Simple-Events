<?php
/**
 * Occurrence source classification.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

/**
 * Describes how one occurrence entered the series projection.
 */
enum OccurrenceSource: string {
	case ONE_OFF = 'one_off';
	case RULE    = 'rule';
	case MANUAL  = 'manual';
}
