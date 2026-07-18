<?php
/**
 * Shared Elementor widget adapter behaviour.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use Elementor\Widget_Base;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;

/**
 * Keeps all widgets on the same renderer, asset and taxonomy boundaries.
 */
abstract class AbstractEventWidget extends Widget_Base {
	/**
	 * Shared native shortcode renderer.
	 *
	 * @var ShortcodeRenderer
	 */
	protected ShortcodeRenderer $renderer;

	/**
	 * Create an Elementor widget while preserving the host constructor contract.
	 *
	 * @param mixed                  $data     Elementor widget data.
	 * @param mixed                  $args     Elementor widget arguments.
	 * @param ShortcodeRenderer|null $renderer Shared native renderer.
	 */
	public function __construct( $data = array(), $args = null, ?ShortcodeRenderer $renderer = null ) { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- The first two parameters mirror Elementor's deliberately untyped API.
		$this->renderer = $renderer ?? $this->default_renderer();

		parent::__construct( $data, $args );
	}

	/**
	 * Return the dedicated Elementor category.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( WidgetRegistrar::CATEGORY );
	}

	/**
	 * Declare the native event stylesheet.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( FrontendAssets::STYLE_HANDLE );
	}

	/**
	 * Opt into Elementor's optimized DOM without its optional inner wrapper.
	 */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Build bounded taxonomy choices for editor controls.
	 *
	 * @param string $taxonomy Event taxonomy name.
	 * @return array<string, string>
	 */
	protected function term_options( string $taxonomy ): array {
		if ( ! in_array( $taxonomy, array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG ), true ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 100,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}

		return $options;
	}

	/** Create the correct renderer when Elementor reconstructs a widget. */
	abstract protected function default_renderer(): ShortcodeRenderer;
}
