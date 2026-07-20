<?php
/**
 * Request-local services for Elementor-reconstructed atomic widgets.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;

/**
 * Keeps independently reconstructed widget objects on one request service set.
 */
final class AtomicWidgetRuntime {
	/**
	 * Shared event-context resolver.
	 *
	 * @var EventContextResolver|null
	 */
	private static ?EventContextResolver $contexts = null;

	/**
	 * Shared named-field renderer.
	 *
	 * @var EventFieldRenderer|null
	 */
	private static ?EventFieldRenderer $fields = null;

	/**
	 * Shared bounded event choices.
	 *
	 * @var PreviewEventOptions|null
	 */
	private static ?PreviewEventOptions $previews = null;

	/** Return the request-shared event-context resolver. */
	public static function contexts(): EventContextResolver {
		return self::$contexts ??= new EventContextResolver();
	}

	/** Return the request-shared field renderer. */
	public static function fields(): EventFieldRenderer {
		return self::$fields ??= new EventFieldRenderer();
	}

	/** Return the request-shared bounded preview choices. */
	public static function previews(): PreviewEventOptions {
		return self::$previews ??= new PreviewEventOptions();
	}
}
