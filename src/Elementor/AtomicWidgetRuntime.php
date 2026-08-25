<?php
/**
 * Request-local services for Elementor-reconstructed atomic widgets.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Shortcode\EventDetailsShortcode;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

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
	 * Shared current event or occurrence resolver.
	 *
	 * @var CurrentEventPresentationResolver|null
	 */
	private static ?CurrentEventPresentationResolver $current = null;

	/**
	 * Shared bounded event choices.
	 *
	 * @var PreviewEventOptions|null
	 */
	private static ?PreviewEventOptions $previews = null;

	/**
	 * Shared complete-details adapter.
	 *
	 * @var ShortcodeRenderer|null
	 */
	private static ?ShortcodeRenderer $details = null;

	/**
	 * Configure the service set used when Elementor reconstructs widget objects.
	 *
	 * @param EventContextResolver             $contexts Shared event resolver.
	 * @param EventFieldRenderer               $fields Shared named fields.
	 * @param CurrentEventPresentationResolver $current Current occurrence adapter.
	 * @param PreviewEventOptions              $previews Bounded editor choices.
	 * @param ShortcodeRenderer                $details Complete details adapter.
	 */
	public static function configure(
		EventContextResolver $contexts,
		EventFieldRenderer $fields,
		CurrentEventPresentationResolver $current,
		PreviewEventOptions $previews,
		ShortcodeRenderer $details
	): void {
		self::$contexts = $contexts;
		self::$fields   = $fields;
		self::$current  = $current;
		self::$previews = $previews;
		self::$details  = $details;
	}

	/** Return the request-shared event-context resolver. */
	public static function contexts(): EventContextResolver {
		return self::$contexts ??= new EventContextResolver();
	}

	/** Return the request-shared field renderer. */
	public static function fields(): EventFieldRenderer {
		return self::$fields ??= new EventFieldRenderer();
	}

	/** Return the request-shared current event or occurrence resolver. */
	public static function current(): CurrentEventPresentationResolver {
		return self::$current ??= new CurrentEventPresentationResolver( self::contexts() );
	}

	/** Return the request-shared bounded preview choices. */
	public static function previews(): PreviewEventOptions {
		return self::$previews ??= new PreviewEventOptions();
	}

	/** Return the request-shared complete-details adapter. */
	public static function details(): ShortcodeRenderer {
		return self::$details ??= new EventDetailsShortcode();
	}
}
