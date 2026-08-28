<?php
/**
 * Calendar category legend visibility.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/** Defines the allowlisted calendar legend behaviour. */
enum CalendarLegendVisibility: string {
	case AUTO = 'auto';
	case SHOW = 'show';
	case HIDE = 'hide';
}
