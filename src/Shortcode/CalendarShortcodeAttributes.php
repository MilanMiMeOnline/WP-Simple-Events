<?php
/**
 * Calendar shortcode attributes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Domain\CalendarView;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;

/**
 * Normalizes the complete allowlisted calendar shortcode contract.
 */
final readonly class CalendarShortcodeAttributes {
	/**
	 * Store normalized calendar choices.
	 *
	 * @param CalendarView $initial_view   Initial desktop view.
	 * @param CalendarView $mobile_view    Initial narrow-screen view.
	 * @param string[]     $category_slugs Event category slugs.
	 * @param string[]     $tag_slugs      Event tag slugs.
	 * @param bool         $filters        Whether filter controls are visible.
	 * @param string       $initial_date   Optional canonical initial date.
	 * @param bool         $show_navigation Show previous and next controls.
	 * @param bool         $show_today     Show the Today control.
	 * @param bool         $show_view_switcher Show month/list controls.
	 * @param string       $fallback_heading_level Server fallback heading element.
	 */
	private function __construct(
		public CalendarView $initial_view,
		public CalendarView $mobile_view,
		public array $category_slugs,
		public array $tag_slugs,
		public bool $filters,
		public string $initial_date,
		public bool $show_navigation,
		public bool $show_today,
		public bool $show_view_switcher,
		public string $fallback_heading_level
	) {}

	/**
	 * Normalize untrusted shortcode attributes through explicit allowlists.
	 *
	 * @param array<string, mixed> $attributes Raw shortcode attributes.
	 */
	public static function from_shortcode( array $attributes ): self {
		return new self(
			CalendarView::tryFrom( self::string_value( $attributes['initial_view'] ?? null ) ) ?? CalendarView::MONTH,
			CalendarView::tryFrom( self::string_value( $attributes['mobile_view'] ?? null ) ) ?? CalendarView::LIST,
			self::slugs( $attributes['category'] ?? '' ),
			self::slugs( $attributes['tag'] ?? '' ),
			self::boolean_value( $attributes['filters'] ?? null, true ),
			self::canonical_date( $attributes['initial_date'] ?? null ),
			self::boolean_value( $attributes['show_navigation'] ?? null, true ),
			self::boolean_value( $attributes['show_today'] ?? null, true ),
			self::boolean_value( $attributes['show_view_switcher'] ?? null, true ),
			self::heading( $attributes['fallback_heading_level'] ?? null )
		);
	}

	/**
	 * Apply allowlisted filters belonging only to this calendar instance.
	 *
	 * @param array<string, mixed> $request Untrusted request data.
	 * @param string               $prefix  Stable instance request prefix.
	 */
	public function with_request( array $request, string $prefix ): self {
		$categories = $this->category_slugs;
		$tags       = $this->tag_slugs;

		if ( $this->filters ) {
			$submitted = '1' === self::string_value( $request[ $prefix . '_apply' ] ?? null )
				|| array_key_exists( $prefix . '_category', $request )
				|| array_key_exists( $prefix . '_tag', $request );

			if ( $submitted ) {
				$categories = self::slugs( $request[ $prefix . '_category' ] ?? array() );
				$tags       = self::slugs( $request[ $prefix . '_tag' ] ?? array() );
			}
		}

		return new self(
			$this->initial_view,
			$this->mobile_view,
			$categories,
			$tags,
			$this->filters,
			$this->initial_date,
			$this->show_navigation,
			$this->show_today,
			$this->show_view_switcher,
			$this->fallback_heading_level
		);
	}

	/**
	 * Build the bounded upcoming criteria used by the no-JavaScript fallback.
	 *
	 * @param int $now_utc Current Unix timestamp.
	 */
	public function fallback_criteria( int $now_utc ): EventQueryCriteria {
		return new EventQueryCriteria(
			EventPeriod::UPCOMING,
			12,
			1,
			$this->category_slugs,
			$this->tag_slugs,
			$now_utc
		);
	}

	/**
	 * Normalize a scalar as a lowercase string.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function string_value( mixed $value ): string {
		return is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
	}

	/**
	 * Normalize a strict shortcode boolean.
	 *
	 * @param mixed $value    Raw value.
	 * @param bool  $fallback Invalid-value fallback.
	 */
	private static function boolean_value( mixed $value, bool $fallback ): bool {
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
	 * Accept only a real canonical Gregorian date.
	 *
	 * @param mixed $value Raw date value.
	 */
	private static function canonical_date( mixed $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $matches ) ) {
			return '';
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
	}

	/**
	 * Normalize the server-fallback heading level.
	 *
	 * @param mixed $value Raw heading value.
	 */
	private static function heading( mixed $value ): string {
		$value = self::string_value( $value );

		return in_array( $value, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $value : 'h3';
	}

	/**
	 * Normalize, deduplicate and bound term slugs.
	 *
	 * @param mixed $value Raw slug input.
	 * @return string[]
	 */
	private static function slugs( mixed $value ): array {
		$values = is_array( $value ) ? $value : explode( ',', is_scalar( $value ) ? (string) $value : '' );
		$slugs  = array();

		foreach ( array_slice( $values, 0, 20 ) as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$slug = sanitize_title( (string) $item );

			if ( '' !== $slug ) {
				$slugs[ $slug ] = $slug;
			}
		}

		return array_values( $slugs );
	}
}
