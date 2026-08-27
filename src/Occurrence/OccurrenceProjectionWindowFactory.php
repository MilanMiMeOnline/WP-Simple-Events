<?php
/**
 * Occurrence projection-window source.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Supplies the complete bounded window used by a production projection write.
 */
interface OccurrenceProjectionWindowFactory {
	/** Return one complete bounded projection window. */
	public function current(): RecurrenceGenerationWindow;
}
