<?php
/**
 * Bounded public event-filter presentation choices.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

/** Keeps editor and shortcode presentation choices out of query logic. */
final readonly class EventFilterPresentation {
	/** Maximum length of one custom public label. */
	private const MAX_LABEL_LENGTH = 80;

	/**
	 * Store normalized filter presentation choices.
	 *
	 * @param bool   $show_categories Show the category choice group.
	 * @param bool   $show_tags       Show the tag choice group.
	 * @param string $layout          Auto, horizontal or stacked layout.
	 * @param string $disclosure      Auto, open or closed initial panel state.
	 * @param bool   $show_chips      Show removable active-choice chips.
	 * @param bool   $show_results    Show the visual result status.
	 * @param string $filter_label    Optional disclosure label.
	 * @param string $period_label    Optional period field label.
	 * @param string $category_label  Optional category group label.
	 * @param string $tag_label       Optional tag group label.
	 * @param string $apply_label     Optional submit-button label.
	 */
	private function __construct(
		public bool $show_categories,
		public bool $show_tags,
		public string $layout,
		public string $disclosure,
		public bool $show_chips,
		public bool $show_results,
		public string $filter_label,
		public string $period_label,
		public string $category_label,
		public string $tag_label,
		public string $apply_label
	) {}

	/**
	 * Normalize a shared shortcode-shaped attribute contract.
	 *
	 * @param array<string, mixed> $attributes           Untrusted attributes.
	 * @param bool                 $default_show_results Host-compatible result default.
	 */
	public static function from_attributes( array $attributes, bool $default_show_results ): self {
		return new self(
			self::boolean( $attributes['filter_categories'] ?? null, true ),
			self::boolean( $attributes['filter_tags'] ?? null, true ),
			self::choice( $attributes['filter_layout'] ?? null, array( 'auto', 'horizontal', 'stacked' ), 'auto' ),
			self::choice( $attributes['filter_disclosure'] ?? null, array( 'auto', 'open', 'closed' ), 'auto' ),
			self::boolean( $attributes['filter_chips'] ?? null, true ),
			self::boolean( $attributes['filter_results'] ?? null, $default_show_results ),
			self::label( $attributes['filter_label'] ?? null ),
			self::label( $attributes['filter_period_label'] ?? null ),
			self::label( $attributes['filter_category_label'] ?? null ),
			self::label( $attributes['filter_tag_label'] ?? null ),
			self::label( $attributes['filter_apply_label'] ?? null )
		);
	}

	/**
	 * Select one documented scalar value.
	 *
	 * @param mixed    $value    Raw value.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Invalid-value fallback.
	 */
	private static function choice( mixed $value, array $allowed, string $fallback ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Normalize one strict boolean without PHP truthiness.
	 *
	 * @param mixed $value    Raw value.
	 * @param bool  $fallback Invalid-value fallback.
	 */
	private static function boolean( mixed $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( ! is_scalar( $value ) ) {
			return $fallback;
		}

		return match ( strtolower( trim( (string) $value ) ) ) {
			'1', 'true', 'yes', 'on' => true,
			'0', 'false', 'no', 'off' => false,
			default => $fallback,
		};
	}

	/**
	 * Normalize one optional bounded plain-text label.
	 *
	 * @param mixed $value Raw label.
	 */
	private static function label( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$label      = trim( sanitize_text_field( (string) $value ) );
		$characters = preg_split( '//u', $label, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $characters ) ? implode( '', array_slice( $characters, 0, self::MAX_LABEL_LENGTH ) ) : '';
	}
}
