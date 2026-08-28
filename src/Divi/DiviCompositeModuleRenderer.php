<?php
/**
 * Shared composite Divi module renderer.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Divi;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\EventFilterStyle;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

/** Delegates Divi settings to the same native renderers used elsewhere. */
final readonly class DiviCompositeModuleRenderer {
	/**
	 * Stable module folders mapped to renderer keys.
	 *
	 * @var array<string, string>
	 */
	public const MODULES = array(
		'event-details'  => 'details',
		'event-list'     => 'list',
		'event-calendar' => 'calendar',
	);

	/**
	 * Create the shared composite renderer.
	 *
	 * @param ShortcodeRenderer $details  Complete event renderer.
	 * @param ShortcodeRenderer $event_list Event list/grid renderer.
	 * @param ShortcodeRenderer $calendar Event calendar renderer.
	 */
	public function __construct(
		private ShortcodeRenderer $details,
		private ShortcodeRenderer $event_list,
		private ShortcodeRenderer $calendar
	) {}

	/**
	 * Render one allowlisted composite module.
	 *
	 * @param string               $component  Internal renderer key.
	 * @param array<string, mixed> $attrs      Untrusted Divi module attributes.
	 * @param int                  $context_id Editor request post, when known.
	 */
	public function render( string $component, array $attrs, int $context_id = 0 ): string {
		$output = match ( $component ) {
			'details'  => $this->details->render( $this->details_settings( $attrs, $context_id ) ),
			'list'     => $this->event_list->render( DiviCompositeSettings::event_list( $attrs ) ),
			'calendar' => $this->calendar->render( DiviCompositeSettings::calendar( $attrs ) ),
			default    => '',
		};

		if ( '' === $output || ! in_array( $component, array( 'list', 'calendar' ), true ) ) {
			return $output;
		}

		$style = EventFilterStyle::from_attributes( DiviCompositeSettings::filter_style( $attrs ) )->inline_style();

		return '' === $style
			? $output
			: '<div class="wpse-divi-filter-style" style="' . esc_attr( $style ) . '">' . $output . '</div>';
	}

	/**
	 * Add an exact public current-event context for isolated REST previews.
	 *
	 * @param array<string, mixed> $attrs      Divi module attributes.
	 * @param int                  $context_id Editor request post.
	 * @return array<string, bool|int|string>
	 */
	private function details_settings( array $attrs, int $context_id ): array {
		$settings = DiviCompositeSettings::details( $attrs );

		if ( ! array_key_exists( 'id', $settings ) && $context_id > 0 && EventPostType::POST_TYPE === get_post_type( $context_id ) ) {
			$settings['id'] = $context_id;
		}

		return $settings;
	}
}
