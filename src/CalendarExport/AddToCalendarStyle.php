<?php
/**
 * Host-neutral add-to-calendar style normalization.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

/** Maps strict builder values to a bounded component-scoped CSS contract. */
final class AddToCalendarStyle {
	/**
	 * Return one strict hexadecimal color or an empty theme-inheriting value.
	 *
	 * @param mixed $value Untrusted host value.
	 */
	public static function color( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^#[0-9a-f]{6}$/Di', $value )
			? strtolower( $value )
			: '';
	}

	/**
	 * Return one optional bounded integer without coercing other shapes.
	 *
	 * @param mixed $value   Untrusted host value.
	 * @param int   $minimum Inclusive lower bound.
	 * @param int   $maximum Inclusive upper bound.
	 */
	public static function integer( mixed $value, int $minimum, int $maximum ): ?int {
		return is_int( $value ) && $value >= $minimum && $value <= $maximum ? $value : null;
	}

	/**
	 * Build a bounded inline custom-property declaration.
	 *
	 * @param array<string, mixed> $values Host-normalized values.
	 */
	public static function inline_style( array $values ): string {
		$properties = array();
		$colors     = array(
			'actionBackground' => '--wpse-calendar-action-background',
			'actionText'       => '--wpse-calendar-action-text',
			'actionBorder'     => '--wpse-calendar-action-border',
			'menuBackground'   => '--wpse-calendar-menu-background',
		);

		foreach ( $colors as $value_key => $property ) {
			$color = self::color( $values[ $value_key ] ?? null );

			if ( '' !== $color ) {
				$properties[] = $property . ':' . $color;
			}
		}

		$dimensions = array(
			'actionRadius'        => array( '--wpse-calendar-action-radius', 0, 100 ),
			'actionGap'           => array( '--wpse-calendar-action-gap', 0, 100 ),
			'menuPadding'         => array( '--wpse-calendar-menu-padding', 0, 100 ),
			'actionPaddingBlock'  => array( '--wpse-calendar-action-padding-block', 0, 100 ),
			'actionPaddingInline' => array( '--wpse-calendar-action-padding-inline', 0, 100 ),
		);

		foreach ( $dimensions as $value_key => [ $property, $minimum, $maximum ] ) {
			$value = self::integer( $values[ $value_key ] ?? null, $minimum, $maximum );

			if ( null !== $value ) {
				$properties[] = $property . ':' . $value . 'px';
			}
		}

		return implode( ';', $properties );
	}
}
