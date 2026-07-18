<?php
/**
 * Event list shortcode attributes.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Domain\EventListView;
use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Frontend\EventCardOptions;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;

/**
 * Normalizes the complete allowlisted shortcode and request contract.
 */
final readonly class EventListAttributes {
	/**
	 * Store normalized shortcode values.
	 *
	 * @param EventListView $view           List or grid layout.
	 * @param EventPeriod   $period         Upcoming, past or all events.
	 * @param int           $limit          Results per page.
	 * @param int           $columns        Grid columns.
	 * @param string[]      $category_slugs Event category slugs.
	 * @param string[]      $tag_slugs      Event tag slugs.
	 * @param bool          $filters        Show interactive filters.
	 * @param bool          $pagination     Show instance pagination.
	 * @param bool          $show_excerpt   Show card excerpts.
	 * @param bool          $show_image     Show card images.
	 * @param bool          $show_location  Show card locations.
	 * @param int           $page           Current instance page.
	 */
	private function __construct(
		public EventListView $view,
		public EventPeriod $period,
		public int $limit,
		public int $columns,
		public array $category_slugs,
		public array $tag_slugs,
		public bool $filters,
		public bool $pagination,
		public bool $show_excerpt,
		public bool $show_image,
		public bool $show_location,
		public int $page
	) {}

	/**
	 * Normalize untrusted shortcode attributes through explicit allowlists.
	 *
	 * @param array<string, mixed> $attributes Raw shortcode attributes.
	 */
	public static function from_shortcode( array $attributes ): self {
		$view   = EventListView::tryFrom( self::string_value( $attributes, 'view' ) ) ?? EventListView::GRID;
		$period = EventPeriod::tryFrom( self::string_value( $attributes, 'period' ) ) ?? EventPeriod::UPCOMING;

		return new self(
			$view,
			$period,
			self::bounded_integer( $attributes['limit'] ?? null, 12, 1, EventQueryCriteria::MAX_LIMIT ),
			self::bounded_integer( $attributes['columns'] ?? null, 3, 1, 4 ),
			self::slugs( $attributes['category'] ?? '' ),
			self::slugs( $attributes['tag'] ?? '' ),
			self::boolean_value( $attributes['filters'] ?? null, false ),
			self::boolean_value( $attributes['pagination'] ?? null, true ),
			self::boolean_value( $attributes['show_excerpt'] ?? null, true ),
			self::boolean_value( $attributes['show_image'] ?? null, true ),
			self::boolean_value( $attributes['show_location'] ?? null, true ),
			1
		);
	}

	/**
	 * Apply namespaced, allowlisted public filter and pagination values.
	 *
	 * @param array<string, mixed> $request Untrusted query parameters.
	 * @param string               $prefix  Stable instance parameter prefix.
	 */
	public function with_request( array $request, string $prefix ): self {
		$period         = $this->period;
		$category_slugs = $this->category_slugs;
		$tag_slugs      = $this->tag_slugs;

		if ( $this->filters ) {
			$requested_period = self::request_scalar( $request, $prefix . '_period' );
			$period           = EventPeriod::tryFrom( $requested_period ) ?? $period;

			if ( array_key_exists( $prefix . '_category', $request ) ) {
				$category_slugs = self::slugs( $request[ $prefix . '_category' ] );
			}

			if ( array_key_exists( $prefix . '_tag', $request ) ) {
				$tag_slugs = self::slugs( $request[ $prefix . '_tag' ] );
			}
		}

		$page = $this->pagination
			? self::bounded_integer( $request[ $prefix . '_page' ] ?? null, 1, 1, EventQueryCriteria::MAX_PAGE )
			: 1;

		return new self(
			$this->view,
			$period,
			$this->limit,
			$this->columns,
			$category_slugs,
			$tag_slugs,
			$this->filters,
			$this->pagination,
			$this->show_excerpt,
			$this->show_image,
			$this->show_location,
			$page
		);
	}

	/**
	 * Build the central repository criteria for the current instance.
	 *
	 * @param int $now_utc Current Unix timestamp.
	 */
	public function criteria( int $now_utc ): EventQueryCriteria {
		return new EventQueryCriteria(
			$this->period,
			$this->limit,
			$this->page,
			$this->category_slugs,
			$this->tag_slugs,
			$now_utc
		);
	}

	/**
	 * Build optional card-section choices.
	 */
	public function card_options(): EventCardOptions {
		return new EventCardOptions( $this->show_excerpt, $this->show_image, $this->show_location );
	}

	/**
	 * Read one scalar attribute as a lowercase string.
	 *
	 * @param array<string, mixed> $attributes Raw attributes.
	 * @param string               $key        Attribute key.
	 */
	private static function string_value( array $attributes, string $key ): string {
		$value = $attributes[ $key ] ?? '';

		return is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
	}

	/**
	 * Normalize a bounded positive integer with a safe fallback.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $fallback Invalid-value fallback.
	 * @param int   $minimum  Minimum value.
	 * @param int   $maximum  Maximum value.
	 */
	private static function bounded_integer( mixed $value, int $fallback, int $minimum, int $maximum ): int {
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return $fallback;
		}

		$integer = (int) $value;

		return $integer >= $minimum && $integer <= $maximum ? $integer : $fallback;
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
	 * Normalize one or more comma-separated or array term slugs.
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

	/**
	 * Read a scalar request parameter.
	 *
	 * @param array<string, mixed> $request Untrusted request data.
	 * @param string               $key     Request key.
	 */
	private static function request_scalar( array $request, string $key ): string {
		$value = $request[ $key ] ?? '';

		return is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
	}
}
