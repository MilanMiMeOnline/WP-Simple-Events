<?php
/**
 * Bounded public event-filter URL state.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/**
 * Preserves only recognized event-component state and renders it safely.
 */
final readonly class EventFilterUrlState {
	private const MAX_VALUES = 80;

	private const MAX_VALUES_PER_FIELD = 20;

	private const MAX_VALUE_LENGTH = 200;

	/**
	 * Normalize allowlisted state belonging to other list or calendar instances.
	 *
	 * @param array<array-key, mixed> $request Current public request values.
	 * @param string                  $prefix  Current component prefix.
	 * @return array<string, string|string[]>
	 */
	public function preserved( array $request, string $prefix ): array {
		$preserved = array();
		$count     = 0;

		foreach ( $request as $key => $value ) {
			if ( $count >= self::MAX_VALUES
				|| ! is_string( $key )
				|| str_starts_with( $key, $prefix . '_' )
				|| 1 !== preg_match( '/^wpse_(?:calendar_)?\d+_(?:apply|period|category|tag|page)$/D', $key ) ) {
				continue;
			}

			$is_multiple = is_array( $value );
			$items       = $is_multiple ? array_slice( $value, 0, self::MAX_VALUES_PER_FIELD ) : array( $value );

			foreach ( $items as $item ) {
				if ( ! is_scalar( $item ) || $count >= self::MAX_VALUES ) {
					continue;
				}

				$clean = substr( sanitize_text_field( (string) $item ), 0, self::MAX_VALUE_LENGTH );

				if ( $is_multiple ) {
					$existing          = is_array( $preserved[ $key ] ?? null ) ? $preserved[ $key ] : array();
					$existing[]        = $clean;
					$preserved[ $key ] = $existing;
				} else {
					$preserved[ $key ] = $clean;
				}
				++$count;
			}
		}

		return $preserved;
	}

	/**
	 * Build one same-page URL from normalized event-filter values.
	 *
	 * @param string                         $action Base page URL.
	 * @param array<string, string|string[]> $values Normalized query values.
	 */
	public function url( string $action, array $values ): string {
		return add_query_arg( $values, $action );
	}

	/**
	 * Render normalized values as escaped hidden form controls.
	 *
	 * @param array<string, string|string[]> $values Normalized query values.
	 */
	public function hidden_fields( array $values ): string {
		$fields = array();

		foreach ( $values as $key => $value ) {
			$items = is_array( $value ) ? $value : array( $value );

			foreach ( $items as $item ) {
				$field_name = is_array( $value ) ? $key . '[]' : $key;
				$fields[]   = sprintf(
					'<input type="hidden" name="%1$s" value="%2$s">',
					esc_attr( $field_name ),
					esc_attr( $item )
				);
			}
		}

		return implode( '', $fields );
	}
}
