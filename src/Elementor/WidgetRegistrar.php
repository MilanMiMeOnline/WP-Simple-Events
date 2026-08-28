<?php
/**
 * Elementor widget and category registration.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Elements_Manager;
use Elementor\Widgets_Manager;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Shortcode\EventDetailsShortcode;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;
use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarRenderer;

/**
 * Registers thin widgets with shared request-wide render services.
 */
final readonly class WidgetRegistrar {
	public const CATEGORY = 'mime-simple-events-calendar';

	/**
	 * Create one request-shared atomic widget service set.
	 *
	 * @param EventContextResolver             $contexts Shared event-context resolver.
	 * @param EventFieldRenderer               $fields   Shared named-field renderer.
	 * @param EditorContext                    $editor   Elementor editor-mode boundary.
	 * @param PreviewEventOptions              $previews Bounded public event choices.
	 * @param CurrentEventPresentationResolver $current Current event or occurrence resolver.
	 * @param ShortcodeRenderer                $details Shared composite details adapter.
	 * @param AddToCalendarRenderer            $calendar_action Shared calendar-action renderer.
	 */
	public function __construct(
		private EventContextResolver $contexts = new EventContextResolver(),
		private EventFieldRenderer $fields = new EventFieldRenderer(),
		private EditorContext $editor = new ElementorEditorContext(),
		private PreviewEventOptions $previews = new PreviewEventOptions(),
		private CurrentEventPresentationResolver $current = new CurrentEventPresentationResolver(),
		private ShortcodeRenderer $details = new EventDetailsShortcode(),
		private AddToCalendarRenderer $calendar_action = new AddToCalendarRenderer()
	) {}

	/**
	 * Register the dedicated MiMe Simple Events and Calendar category.
	 *
	 * @param Elements_Manager $manager Elementor elements manager.
	 */
	public function register_category( Elements_Manager $manager ): void {
		$manager->add_category(
			self::CATEGORY,
			array(
				'title' => esc_html__( 'MiMe Simple Events and Calendar', 'mime-simple-events-calendar' ),
				'icon'  => 'eicon-calendar',
			)
		);
	}

	/**
	 * Register all required widgets through Elementor's current API.
	 *
	 * @param Widgets_Manager $manager Elementor widgets manager.
	 */
	public function register_widgets( Widgets_Manager $manager ): void {
		AtomicWidgetRuntime::configure( $this->contexts, $this->fields, $this->current, $this->previews, $this->details );
		$manager->register( new EventListWidget() );
		$manager->register( new EventCalendarWidget() );
		$manager->register( new EventDetailsWidget( renderer: $this->details, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new AddToCalendarWidget( renderer: $this->calendar_action, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventTitleWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventFeaturedImageWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventDateTimeWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventStatusWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventVenueWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventAddressWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventLocationLinkWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventContentWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventExcerptWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventExternalActionWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventCategoriesWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
		$manager->register( new EventTagsWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews, current: $this->current ) );
	}
}
