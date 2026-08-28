<?php
/**
 * Strict editor filter-style variables.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/** Converts bounded editor values to component-scoped CSS custom properties. */
final readonly class EventFilterStyle {
	/**
	 * Color attribute to CSS-property mapping.
	 *
	 * @var array<string, string>
	 */
	private const COLORS = array(
		'filterContainerBackground' => '--wpse-filter-background',
		'filterPanelBackground'     => '--wpse-filter-panel-background',
		'filterTriggerBackground'   => '--wpse-filter-trigger-background',
		'filterTriggerText'         => '--wpse-filter-trigger-text',
		'filterFieldBackground'     => '--wpse-control-background',
		'filterFieldText'           => '--wpse-control-text',
		'filterAccent'              => '--wpse-filter-accent',
		'filterChipBackground'      => '--wpse-filter-chip-background',
		'filterChipText'            => '--wpse-filter-chip-text',
		'filterActionBackground'    => '--wpse-filter-action-background',
		'filterActionText'          => '--wpse-filter-action-text',
		'filterStatusBackground'    => '--wpse-filter-status-background',
		'filterStatusText'          => '--wpse-filter-status-text',
	);

	/**
	 * Pixel attribute to property and bounds mapping.
	 *
	 * @var array<string, array{0: string, 1: int, 2: int}>
	 */
	private const PIXELS = array(
		'filterGap'              => array( '--wpse-filter-gap', 0, 80 ),
		'filterContainerPadding' => array( '--wpse-filter-padding', 0, 80 ),
		'filterPanelPadding'     => array( '--wpse-filter-panel-padding', 0, 80 ),
		'filterPanelRadius'      => array( '--wpse-filter-panel-radius', 0, 80 ),
		'filterTriggerPadding'   => array( '--wpse-filter-trigger-padding', 0, 80 ),
		'filterTriggerRadius'    => array( '--wpse-filter-trigger-radius', 0, 80 ),
		'filterOptionGap'        => array( '--wpse-filter-option-gap', 0, 40 ),
		'filterCheckboxSize'     => array( '--wpse-filter-checkbox-size', 8, 40 ),
		'filterOptionsMaxHeight' => array( '--wpse-filter-options-max-height', 80, 800 ),
		'filterChipPadding'      => array( '--wpse-filter-chip-padding', 0, 80 ),
		'filterChipRadius'       => array( '--wpse-filter-chip-radius', 0, 80 ),
		'filterActionPadding'    => array( '--wpse-filter-action-padding', 0, 80 ),
		'filterActionRadius'     => array( '--wpse-filter-action-radius', 0, 80 ),
		'filterStatusPadding'    => array( '--wpse-filter-status-padding', 0, 80 ),
	);

	/**
	 * Store valid normalized CSS variables.
	 *
	 * @param array<string, string> $variables Valid normalized CSS variables.
	 */
	private function __construct( private array $variables ) {}

	/**
	 * Normalize schema-shaped editor attributes.
	 *
	 * @param array<string, mixed> $attributes Untrusted builder attributes.
	 */
	public static function from_attributes( array $attributes ): self {
		$variables = array();

		foreach ( self::COLORS as $key => $property ) {
			$value = $attributes[ $key ] ?? null;

			if ( is_string( $value ) && 1 === preg_match( '/^#[0-9a-f]{6}$/Di', $value ) ) {
				$variables[ $property ] = strtolower( $value );
			}
		}

		foreach ( self::PIXELS as $key => $definition ) {
			$value = $attributes[ $key ] ?? null;

			if ( is_int( $value ) && $value >= $definition[1] && $value <= $definition[2] ) {
				$variables[ $definition[0] ] = $value . 'px';
			}
		}

		return new self( $variables );
	}

	/** Return an escaped-late style value composed only of normalized declarations. */
	public function inline_style(): string {
		$declarations = array();

		foreach ( $this->variables as $property => $value ) {
			$declarations[] = $property . ':' . $value;
		}

		return implode( ';', $declarations );
	}
}
