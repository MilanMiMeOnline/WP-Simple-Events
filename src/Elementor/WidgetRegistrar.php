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

/**
 * Registers thin widgets with shared request-wide render services.
 */
final readonly class WidgetRegistrar {
	public const CATEGORY = 'wp-simple-events';

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
		$manager->register( new EventDetailsWidget() );
	}
}
