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
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;

/**
 * Registers thin widgets with shared request-wide render services.
 */
final readonly class WidgetRegistrar {
	public const CATEGORY = 'wp-simple-events';

	/**
	 * Create one request-shared atomic widget service set.
	 *
	 * @param EventContextResolver $contexts Shared event-context resolver.
	 * @param EventFieldRenderer   $fields   Shared named-field renderer.
	 * @param EditorContext        $editor   Elementor editor-mode boundary.
	 * @param PreviewEventOptions  $previews Bounded public event choices.
	 */
	public function __construct(
		private EventContextResolver $contexts = new EventContextResolver(),
		private EventFieldRenderer $fields = new EventFieldRenderer(),
		private EditorContext $editor = new ElementorEditorContext(),
		private PreviewEventOptions $previews = new PreviewEventOptions()
	) {}

	/**
	 * Register the dedicated WP Simple Events category.
	 *
	 * @param Elements_Manager $manager Elementor elements manager.
	 */
	public function register_category( Elements_Manager $manager ): void {
		$manager->add_category(
			self::CATEGORY,
			array(
				'title' => esc_html__( 'WP Simple Events', 'wp-simple-events' ),
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
		$manager->register( new EventListWidget() );
		$manager->register( new EventCalendarWidget() );
		$manager->register( new EventDetailsWidget( editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventTitleWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventFeaturedImageWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventDateTimeWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventStatusWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventVenueWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventAddressWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventLocationLinkWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventContentWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventExcerptWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventExternalActionWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventCategoriesWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
		$manager->register( new EventTagsWidget( contexts: $this->contexts, fields: $this->fields, editor: $this->editor, previews: $this->previews ) );
	}
}
