<?php
/**
 * Event validation error codes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

/**
 * Stable error codes safe for redirects and API responses.
 */
enum EventValidationError: string {
	case MISSING_START_DATE   = 'missing_start_date';
	case MISSING_START_TIME   = 'missing_start_time';
	case INVALID_START        = 'invalid_start';
	case INVALID_END          = 'invalid_end';
	case INCOMPLETE_END       = 'incomplete_end';
	case INVALID_DATE_RANGE   = 'invalid_date_range';
	case INVALID_TIMEZONE     = 'invalid_timezone';
	case INVALID_STATUS       = 'invalid_status';
	case INVALID_LOCATION_URL = 'invalid_location_url';
	case INVALID_EVENT_URL    = 'invalid_event_url';
}
